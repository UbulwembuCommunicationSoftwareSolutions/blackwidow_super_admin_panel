<?php

namespace App\Console\Commands\OneTimeFixes;

use Illuminate\Console\Command;

class ChangeDeploymentScriptForConsoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:change-deployment-script-for-consoles';

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
        $customer = $this->ask('Enter the customer id');
        $consoles = \App\Models\CustomerSubscription::where('customer_id',$customer)->where('subscription_type_id', 1)->get();
        foreach($consoles as $console){
            try{
                $deploymentScript = $console->deploymentScript()->first();
                if($deploymentScript){
                    $deploymentScript->script = preg_replace(
                        '/rm -rf node_modules package-lock\.json yarn\.lock\n\. ~/.nvm\/nvm\.sh\nnvm use 20\n# Install NPM dependencies and build assets\nnpm install && npm run build\n?/',
                        '',
                        $deploymentScript->script
                    );
                    $deploymentScript->save();
                }
            }catch (\Exception $e){
                echo $e->getMessage();
            }

        }
    }
}
