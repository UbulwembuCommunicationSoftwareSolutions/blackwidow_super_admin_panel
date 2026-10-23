<?php

namespace App\Console\Commands;

use App\Models\DeploymentTemplate;
use App\Models\SubscriptionType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Loads the Forge zero-downtime (release based) deployment scripts from
 * resources/deployment-templates/native/<subscription-type-name-slug>.sh into
 * deployment_templates.native_script.
 *
 * Sites of a type with a native script are created as native zero-downtime sites; types without
 * one keep being created as classic sites. Existing sites are not touched: their stored script is
 * only replaced for subscriptions flagged zero_downtime.
 */
class LoadNativeDeploymentTemplates extends Command
{
    protected $signature = 'app:load-native-deployment-templates {--dry-run : Show what would change without saving}';

    protected $description = 'Load the zero-downtime deployment scripts into the deployment templates';

    public function handle(): int
    {
        foreach (SubscriptionType::query()->orderBy('id')->get() as $type) {
            $file = resource_path('deployment-templates/native/'.Str::slug($type->name).'.sh');

            if (! is_file($file)) {
                $this->line("skip   #{$type->id} {$type->name}: no native script (stays a classic site)");

                continue;
            }

            $script = (string) file_get_contents($file);

            if (! str_contains($script, '$CREATE_RELEASE()') || ! str_contains($script, '$ACTIVATE_RELEASE()')) {
                $this->error("{$file} is missing the \$CREATE_RELEASE() / \$ACTIVATE_RELEASE() macros");

                return self::FAILURE;
            }

            $template = DeploymentTemplate::query()->firstOrNew(['subscription_type_id' => $type->id]);
            $verb = $template->exists ? 'update' : 'create';

            $this->info("{$verb} #{$type->id} {$type->name}");

            if ($this->option('dry-run')) {
                continue;
            }

            // A type that had no template yet is new-sites-only, so its classic script is the native one too.
            if (! $template->exists) {
                $template->script = $script;
            }
            $template->native_script = $script;
            $template->save();
        }

        return self::SUCCESS;
    }
}
