<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportExistingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-existing-users';

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
        $consoles = \App\Models\CustomerSubscription::where('subscription_type_id', 1)->get();
        foreach($consoles as $console){
            $database = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_DATABASE')->first();
            $user = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_USERNAME')->first();
            $password = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_PASSWORD')->first();
            try{
                $mysqli = new \mysqli("localhost", $user->value, $password->value, $database->value);
                $result = $mysqli->query("SELECT * FROM users");
                while($row = $result->fetch_assoc()){
                    $user = \App\Models\CustomerUser::updateOrCreate([
                        'email_address' => $row['email'],
                        'customer_id' => $console->customer_id,
                    ],[
                        'first_name' => $row['name'],
                        'last_name' => $row['surname'],
                        'password' => $row['password']
                    ]);
                }
            }catch (\Exception $e){
                echo $e->getMessage();
            }

        }
    }
}
