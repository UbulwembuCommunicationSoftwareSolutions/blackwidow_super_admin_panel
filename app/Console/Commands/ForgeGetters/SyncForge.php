<?php

namespace App\Console\Commands\ForgeGetters;

use App\Helpers\ForgeApi;
use Illuminate\Console\Command;

class SyncForge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-forge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $forgeApi = new ForgeApi();
        $forgeApi->syncForge();
    }
}
