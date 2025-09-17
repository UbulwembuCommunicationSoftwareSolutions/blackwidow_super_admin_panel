<?php

namespace App\Console\Commands\OneTimeFixes;

use App\Models\CustomerSubscription;
use Exception;
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
        $consoles = CustomerSubscription::where('subscription_type_id', 1)->get();
        foreach($consoles as $console){
            try{
                $deploymentScript = $console->deploymentScript()->first();
                if($deploymentScript){
                    $deploymentScript->script = preg_replace(
                        '/rm -rf node_modules package-lock\.json yarn\.lock\s*\R\. ~/.nvm\/nvm\.sh\s*\Rnvm use 20\s*\R# Install NPM dependencies and build assets\s*\Rnpm install && npm run build\s*\R?/m',
                        '',
                        $deploymentScript->script
                    );
                    $deploymentScript->save();
                }
            }catch (Exception $e){
                echo $e->getMessage();
            }

        }
    }
}
