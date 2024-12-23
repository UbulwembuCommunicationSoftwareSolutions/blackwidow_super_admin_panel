<?php

namespace App\Console\Commands\SiteDeployment;

use App\Jobs\SendCommandToForgeJob;
use Illuminate\Console\Command;

class SendCommandToSite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-command-to-site {site-id} {command}';

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
        SendCommandToForgeJob::dispatch($this->argument('site-id'), $this->argument('command'));
        $this->info('Command has been dispatched to all consoles.');
        return Command::SUCCESS;
    }
}
