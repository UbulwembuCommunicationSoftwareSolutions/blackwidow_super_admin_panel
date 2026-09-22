<?php

namespace App\Console\Commands\OneTimeFixes;

use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

/**
 * Rewrites deployment templates to check out a concrete GitHub release tag instead of
 * pulling the Forge branch tip, and appends VERSION / VERSION_SHA / BW_DEPLOYED_RELEASE
 * markers used by DeploySite confirmation.
 *
 * After running this, re-render scripts with `app:send-all-sites-deployment {type-id}`
 * (or Upgrade actions) so live Forge sites pick up the new script.
 */
class ConvertDeploymentTemplatesToReleaseTags extends Command
{
    protected $signature = 'app:convert-deployment-templates-to-release-tags {--dry-run : Show changes without saving}';

    protected $description = 'Replace git pull with git checkout #RELEASE_TAG# and append deploy confirmation markers';

    public function handle(): int
    {
        $templates = DeploymentTemplate::query()->with('subscriptionType')->get();
        if ($templates->isEmpty()) {
            $this->warn('No deployment templates found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($templates as $template) {
            $label = ($template->subscriptionType->name ?? 'unknown').' (template '.$template->id.')';
            $updated = $this->convert($template->script);

            if ($updated === $template->script) {
                $this->info("{$label}: already converted or unrecognized shape, skipping.");

                continue;
            }

            if ($dryRun) {
                $this->line("--- {$label} (dry-run) ---");
                $this->line($updated);

                continue;
            }

            $template->script = $updated;
            $template->save();
            $this->info("{$label}: updated.");
        }

        return self::SUCCESS;
    }

    private function convert(string $script): string
    {
        if (str_contains($script, '#RELEASE_TAG#') && str_contains($script, 'BW_DEPLOYED_RELEASE')) {
            return $script;
        }

        $converted = $script;

        // Prefer FORGE_SITE_PATH when the script still hardcodes /home/forge/#WEBSITE_URL#.
        $converted = preg_replace(
            '~^cd\s+/home/forge/#WEBSITE_URL#\s*$~m',
            'cd $FORGE_SITE_PATH',
            $converted,
            1
        ) ?? $converted;

        // Replace branch pull with tag fetch + checkout.
        $converted = preg_replace(
            '~^git\s+pull\s+origin\s+\$FORGE_SITE_BRANCH\s*$~m',
            "git fetch --tags --force origin\ngit checkout --force #RELEASE_TAG#",
            $converted,
            1,
            $count
        ) ?? $converted;

        if ($count !== 1 && ! str_contains($converted, 'git checkout --force #RELEASE_TAG#')) {
            // Unrecognized shape — leave unchanged so operators can convert manually.
            return $script;
        }

        $markerBlock = <<<'BASH'

echo "#RELEASE_TAG#" > VERSION
echo "$(git rev-parse HEAD)" > VERSION_SHA
echo "BW_DEPLOYED_RELEASE #RELEASE_TAG# $(git rev-parse HEAD)"
BASH;

        if (! str_contains($converted, 'BW_DEPLOYED_RELEASE')) {
            $converted = rtrim($converted)."\n".$markerBlock."\n";
        }

        return $converted;
    }
}
