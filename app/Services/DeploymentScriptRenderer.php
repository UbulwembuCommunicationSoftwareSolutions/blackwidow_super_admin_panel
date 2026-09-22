<?php

namespace App\Services;

use App\Helpers\ForgeApi;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\DeploymentTemplate;
use RuntimeException;

class DeploymentScriptRenderer
{
    /**
     * Identifies the lock preamble in an already rendered script so re-rendering never stacks a second one.
     */
    private const DEPLOY_LOCK_MARKER = 'bw-deploy-lock';

    /**
     * How long a queued deploy waits for the running one before giving up. Kept below the 240s
     * {@see DeploySite} spends waiting on Forge, so a queued deploy still
     * finishes inside the window its own pipeline step is watching.
     */
    private const DEPLOY_LOCK_WAIT_SECONDS = 180;

    /**
     * Render the deployment script for a subscription from its template (or custom script),
     * substituting #WEBSITE_URL# and #RELEASE_TAG#, and optionally push it to Forge.
     *
     * @return array{0: DeploymentScript, 1: bool} Script and whether Forge was updated.
     */
    public function renderAndPush(CustomerSubscription $customerSubscription, bool $pushToForge = true): array
    {
        [$script, $changed] = $this->render($customerSubscription);

        if (! $pushToForge || ! $changed) {
            return [$script, false];
        }

        if (! $customerSubscription->server_id || ! $customerSubscription->forge_site_id) {
            return [$script, false];
        }

        $forgeApi = new ForgeApi;
        $forgeApi->sendDeploymentScript($customerSubscription);

        return [$script, true];
    }

    /**
     * @return array{0: DeploymentScript, 1: bool} Script and whether the stored script changed.
     */
    public function render(CustomerSubscription $customerSubscription): array
    {
        $target = $customerSubscription->targetRelease();
        $existing = $customerSubscription->deploymentScript()->first();

        if ($existing?->is_custom) {
            $source = (string) $existing->script;
        } else {
            $template = DeploymentTemplate::query()
                ->where('subscription_type_id', $customerSubscription->subscription_type_id)
                ->first();

            if (! $template) {
                throw new RuntimeException(
                    'No deployment template for subscription type '.$customerSubscription->subscription_type_id.
                    ' (customer subscription '.$customerSubscription->id.').'
                );
            }

            $source = (string) $template->script;
        }

        if (str_contains($source, '#RELEASE_TAG#') && $target === null) {
            throw new RuntimeException(
                "No target release for subscription {$customerSubscription->id}."
            );
        }

        if ($target !== null && $target->is_draft) {
            throw new RuntimeException(
                "Cannot deploy release {$target->tag} for subscription {$customerSubscription->id}: release is no longer on GitHub (marked draft)."
            );
        }

        $domain = $this->websitePlaceholder($customerSubscription);
        $tag = $target?->tag ?? '';

        $rendered = str_replace(
            ['#WEBSITE_URL#', '#RELEASE_TAG#'],
            [$domain, $tag],
            $source
        );

        $rendered = $this->withDeploymentLock($rendered, $domain);

        $releaseId = $target?->id;

        if (
            $existing
            && $existing->script === $rendered
            && (int) $existing->rendered_release_id === (int) $releaseId
        ) {
            return [$existing, false];
        }

        $script = DeploymentScript::query()->updateOrCreate(
            ['customer_subscription_id' => $customerSubscription->id],
            [
                'script' => $rendered,
                'rendered_release_id' => $releaseId,
                'is_custom' => (bool) ($existing?->is_custom ?? false),
            ]
        );

        return [$script, true];
    }

    /**
     * Hold a per-site lock for the duration of the deploy so a second Forge deployment for the
     * same site waits instead of running git and composer in the directory at the same time. Two
     * overlapping composer runs rewrite vendor/composer/autoload_classmap.php while the other is
     * reading it, which aborts the deploy with a ClassLoader::addClassMap() TypeError.
     */
    private function withDeploymentLock(string $script, string $domain): string
    {
        if ($domain === '' || str_contains($script, self::DEPLOY_LOCK_MARKER)) {
            return $script;
        }

        $marker = self::DEPLOY_LOCK_MARKER;
        $lockFile = '/tmp/'.$marker.'-'.preg_replace('/[^A-Za-z0-9._-]/', '-', $domain).'.lock';
        $waitSeconds = self::DEPLOY_LOCK_WAIT_SECONDS;

        $preamble = <<<BASH
        # {$marker}: one deploy at a time per site. Concurrent deploys run git and composer in the
        # same directory, corrupting composer's generated autoload files and aborting the deploy.
        exec 9>"{$lockFile}"
        flock -w {$waitSeconds} 9 || { echo "Another deployment is already running for {$domain}; aborting."; exit 1; }

        BASH;

        $firstNewline = strpos($script, "\n");

        if (str_starts_with($script, '#!') && $firstNewline !== false) {
            return substr($script, 0, $firstNewline + 1)."\n".$preamble.substr($script, $firstNewline + 1);
        }

        return $preamble.$script;
    }

    private function websitePlaceholder(CustomerSubscription $customerSubscription): string
    {
        if (filled($customerSubscription->domain)) {
            return (string) $customerSubscription->domain;
        }

        $baseUrl = str_replace(['https://', 'http://'], '', (string) $customerSubscription->url);

        return $baseUrl;
    }
}
