<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommandToForgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $customerSubscriptionId;

    public $command;
    /**
     * Create a new job instance.
     */
    public function __construct($customerSubscriptionId,$command)
    {
        $this->command = $command;
        $this->customerSubscriptionId = $customerSubscriptionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $forgeApi = new \App\Helpers\ForgeApi();
        $forgeApi->sendCommand($this->customerSubscriptionId,$this->command);
    }
}
