<?php

namespace App\Console\Commands;

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
        $forgeApi = new \App\Helpers\ForgeApi();
        $forgeApi->syncForge();
    }
}
