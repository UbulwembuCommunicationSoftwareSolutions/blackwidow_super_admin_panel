<?php

namespace App\Console\Commands\ForgeGetters;

use App\Services\ForgeServerSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncForgeServersFromApi extends Command
{
    protected $signature = 'app:sync-forge-servers';

    protected $description = 'Fetch Forge servers for the current API key and upsert them into my_forge_servers';

    public function handle(): int
    {
        try {
            $count = ForgeServerSyncService::syncFromApi();
            $this->info("Synced {$count} server(s) from Laravel Forge.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
