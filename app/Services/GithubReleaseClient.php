<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubReleaseClient
{
    private const API_BASE = 'https://api.github.com';

    private const ACCEPT = 'application/vnd.github+json';

    private const USER_AGENT = 'blackwidow-super-admin';

    /**
     * List published releases for a repository (owner/repo), paginating through GitHub.
     * Skips drafts unless $includeDrafts is true. Uses ETag conditional requests when available.
     *
     * @return list<array{
     *     id: int,
     *     tag_name: string,
     *     name: ?string,
     *     body: ?string,
     *     draft: bool,
     *     prerelease: bool,
     *     published_at: ?string,
     *     commit_sha: string
     * }>
     */
    public function listReleases(string $repository, bool $includeDrafts = false): array
    {
        $repository = $this->normalizeRepository($repository);
        $releases = [];
        $page = 1;

        do {
            $cacheKey = "github.releases.etag.{$repository}.{$page}";
            $etag = Cache::get($cacheKey);
            $request = $this->http();
            if (is_string($etag) && $etag !== '') {
                $request = $request->withHeaders(['If-None-Match' => $etag]);
            }

            $response = $request->get(self::API_BASE."/repos/{$repository}/releases", [
                'per_page' => 100,
                'page' => $page,
            ]);

            if ($response->status() === 304) {
                $cached = Cache::get("github.releases.body.{$repository}.{$page}");
                $batch = is_array($cached) ? $cached : [];
            } else {
                $response->throw();
                $batch = $response->json() ?? [];
                if (! is_array($batch)) {
                    $batch = [];
                }

                $newEtag = $response->header('ETag');
                if (is_string($newEtag) && $newEtag !== '') {
                    Cache::put($cacheKey, $newEtag, now()->addDay());
                    Cache::put("github.releases.body.{$repository}.{$page}", $batch, now()->addDay());
                }
            }

            foreach ($batch as $release) {
                if (! is_array($release)) {
                    continue;
                }
                if (! $includeDrafts && ($release['draft'] ?? false)) {
                    continue;
                }

                $tag = (string) ($release['tag_name'] ?? '');
                if ($tag === '') {
                    continue;
                }

                $releases[] = [
                    'id' => (int) ($release['id'] ?? 0),
                    'tag_name' => $tag,
                    'name' => isset($release['name']) ? (string) $release['name'] : null,
                    'body' => isset($release['body']) ? (string) $release['body'] : null,
                    'draft' => (bool) ($release['draft'] ?? false),
                    'prerelease' => (bool) ($release['prerelease'] ?? false),
                    'published_at' => isset($release['published_at']) ? (string) $release['published_at'] : null,
                    'commit_sha' => $this->resolveTagCommitSha($repository, $tag),
                ];
            }

            $page++;
            $hasMore = count($batch) === 100;
        } while ($hasMore);

        return $releases;
    }

    /**
     * Resolve an annotated or lightweight tag to its commit SHA.
     */
    public function resolveTagCommitSha(string $repository, string $tag): string
    {
        $repository = $this->normalizeRepository($repository);
        $encodedTag = rawurlencode($tag);

        try {
            $response = $this->http()->get(self::API_BASE."/repos/{$repository}/git/ref/tags/{$encodedTag}");
            $response->throw();
        } catch (RequestException $e) {
            throw new RuntimeException(
                "Unable to resolve GitHub tag {$tag} on {$repository}: ".$e->getMessage(),
                previous: $e
            );
        }

        $data = $response->json();
        $object = is_array($data) ? ($data['object'] ?? null) : null;
        if (! is_array($object) || blank($object['sha'] ?? null)) {
            throw new RuntimeException("GitHub tag {$tag} on {$repository} has no object SHA.");
        }

        $sha = (string) $object['sha'];
        $type = (string) ($object['type'] ?? 'commit');

        if ($type === 'commit') {
            return $sha;
        }

        // Annotated tags point at a tag object; dereference to the commit.
        try {
            $tagResponse = $this->http()->get(self::API_BASE."/repos/{$repository}/git/tags/{$sha}");
            $tagResponse->throw();
        } catch (RequestException $e) {
            throw new RuntimeException(
                "Unable to dereference annotated tag {$tag} on {$repository}: ".$e->getMessage(),
                previous: $e
            );
        }

        $tagData = $tagResponse->json();
        $commitSha = is_array($tagData)
            ? (string) data_get($tagData, 'object.sha', '')
            : '';

        if ($commitSha === '' || strlen($commitSha) !== 40) {
            throw new RuntimeException("Annotated tag {$tag} on {$repository} did not resolve to a commit SHA.");
        }

        return $commitSha;
    }

    private function http(): PendingRequest
    {
        $token = config('services.github.token');
        if (blank($token)) {
            throw new RuntimeException(
                'GitHub is not configured: set GITHUB_TOKEN in your .env.'
            );
        }

        return Http::withToken((string) $token)
            ->accept(self::ACCEPT)
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => self::USER_AGENT,
            ])
            ->timeout(30);
    }

    private function normalizeRepository(string $repository): string
    {
        $repository = trim($repository);
        $repository = preg_replace('#^https?://github\.com/#i', '', $repository) ?? $repository;
        $repository = rtrim($repository, '/');
        $repository = preg_replace('#\.git$#i', '', $repository) ?? $repository;

        if (! preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
            throw new RuntimeException("Invalid GitHub repository: {$repository}");
        }

        return $repository;
    }
}
