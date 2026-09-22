<?php

namespace App\Console\Commands\OneTimeFixes;

use App\Models\DeploymentTemplate;
use Illuminate\Console\Command;

/**
 * Console (blackwidow_cms) and Firearm (firearm-v4) both now ship
 * `permissions:sync-from-policies`, which keeps each product's permission set in sync with its
 * Filament policies. Nothing calls it after deploy, so a permission added or changed in a policy
 * never reaches a live site until someone runs it by hand over SSH.
 *
 * Bakes an idempotent `artisan permissions:sync-from-policies` into the Console and Firearm
 * deployment templates, right after the existing artisan step(s), so it self-applies on every
 * recurring deploy. Run `app:send-all-sites-deployment {type-id}` afterwards for each type to
 * regenerate and push the updated script to already-live sites on Forge.
 */
class AddPermissionsSyncToDeploymentTemplates extends Command
{
    protected $signature = 'app:add-permissions-sync-to-deployment-templates';

    protected $description = 'Insert `artisan permissions:sync-from-policies` into the Console and Firearm deployment templates';

    public function handle(): void
    {
        $templates = DeploymentTemplate::query()
            ->whereHas('subscriptionType', fn ($q) => $q->where('name', 'Console')->orWhere('name', 'like', '%Firearm%'))
            ->with('subscriptionType')
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('No deployment templates found for the Console or Firearm subscription types.');

            return;
        }

        foreach ($templates as $template) {
            $label = $template->subscriptionType->name.' (template '.$template->id.')';

            if (str_contains($template->script, 'permissions:sync-from-policies')) {
                $this->info("{$label} already has permissions:sync-from-policies, skipping.");

                continue;
            }

            // Insert right after the last artisan call inside the `if [ -f artisan ]; then ... fi`
            // block (e.g. migrate, or migrate + storage:link if that fix already ran here).
            $updated = preg_replace(
                '/(if \[ -f artisan \]; then\s*\R(?:\s*\$FORGE_PHP artisan [^\r\n]*\s*\R)+)(fi)/',
                '$1    $FORGE_PHP artisan permissions:sync-from-policies'.PHP_EOL.'$2',
                $template->script,
                1,
                $count
            );

            if ($count !== 1) {
                $this->warn("{$label} did not match the expected artisan block, skipping. Add the line manually.");

                continue;
            }

            $template->script = $updated;
            $template->save();
            $this->info("{$label} updated.");
        }
    }
}
