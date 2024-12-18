<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GetSitesForServerJob implements ShouldQueue
{
    use Queueable;

    public $serverId;
    /**
     * Create a new job instance.
     */
    public function __construct($serverId)
    {
        $this->serverId = $serverId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $forge = new ForgeApi();
        $forge->getSitesForServer($this->serverId);
    }
}
