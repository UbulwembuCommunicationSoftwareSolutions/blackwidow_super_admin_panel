<?php

namespace App\Jobs;

use App\Helpers\ForgeApi;
use Laravel\Forge\Forge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TriggerForgeDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $serverId;
    protected $siteId;

    /**
     * Create a new job instance.
     *
     * @param int $serverId
     * @param int $siteId
     */
    public function __construct($serverId, $siteId)
    {
        $this->serverId = $serverId;
        $this->siteId = $siteId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $forge = new ForgeApi();// Or resolve it however you manage the Forge SDK
        $forge->deploySite($this->serverId, $this->siteId);
    }
}
