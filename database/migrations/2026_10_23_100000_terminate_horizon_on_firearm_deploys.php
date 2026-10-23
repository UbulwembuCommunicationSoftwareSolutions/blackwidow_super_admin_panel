<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Firearm sites now run Horizon. Deployments must terminate the previous
 * master after the new release is active, the same way Console does.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('deployment_templates')->where('subscription_type_id', 2)->first();

        if ($template === null || str_contains((string) $template->script, 'horizon:terminate')) {
            return;
        }

        $script = str_replace(
            "\$RESTART_QUEUES()\n",
            "\$RESTART_QUEUES()\n\$FORGE_PHP artisan horizon:terminate\n",
            (string) $template->script
        );

        if ($script === $template->script) {
            return;
        }

        DB::table('deployment_templates')->where('id', $template->id)->update([
            'script' => $script,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $template = DB::table('deployment_templates')->where('subscription_type_id', 2)->first();

        if ($template === null) {
            return;
        }

        $script = str_replace(
            "\$RESTART_QUEUES()\n\$FORGE_PHP artisan horizon:terminate\n",
            "\$RESTART_QUEUES()\n",
            (string) $template->script
        );

        DB::table('deployment_templates')->where('id', $template->id)->update([
            'script' => $script,
            'updated_at' => now(),
        ]);
    }
};
