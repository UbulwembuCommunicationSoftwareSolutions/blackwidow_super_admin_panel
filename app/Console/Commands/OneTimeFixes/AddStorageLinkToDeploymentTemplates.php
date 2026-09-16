<?php

namespace App\Console\Commands\OneTimeFixes;

use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

/**
 * `storage:link` was only ever run once, during initial site provisioning
 * (SiteDeploymentScheduler's one-off pipeline step). If that symlink is ever
 * lost — a server rebuild, a manual site recreation, a domain rename done
 * outside the normal pipeline — nothing recreates it, and every synced asset
 * under /storage silently 404s. Found via customer_subscription_id 249
 * (demo-ciid.console.aims.net.za), whose branding logos were unreachable
 * because the symlink was simply missing.
 *
 * This bakes an idempotent `storage:link` into every recurring deploy for
 * Laravel-backed subscription types, so it self-heals on the next deploy
 * instead of requiring another manual SSH fix.
 */
class AddStorageLinkToDeploymentTemplates extends Command
{
    protected $signature = 'app:add-storage-link-to-deployment-templates';

    protected $description = 'Insert `artisan storage:link` into every Laravel-backed deployment template, right after migrate';

    public function handle(): void
    {
        $templates = DeploymentTemplate::query()
            ->where('script', 'like', '%if [ -f artisan ]%')
            ->get();

        foreach ($templates as $template) {
            if (str_contains($template->script, 'storage:link')) {
                $this->info("Template {$template->id} (type {$template->subscription_type_id}) already has storage:link, skipping.");

                continue;
            }

            $updated = preg_replace(
                '/(if \[ -f artisan \]; then\s*\R\s*\$FORGE_PHP artisan migrate --force\s*\R)(fi)/',
                '$1    $FORGE_PHP artisan storage:link'.PHP_EOL.'$2',
                $template->script,
                1,
                $count
            );

            if ($count !== 1) {
                $this->warn("Template {$template->id} (type {$template->subscription_type_id}) did not match the expected migrate block, skipping.");

                continue;
            }

            $template->script = $updated;
            $template->save();
            $this->info("Template {$template->id} (type {$template->subscription_type_id}) updated.");
        }
    }
}
