<?php

namespace App\Console\Commands\Importers;

use App\Models\CustomerSubscription;
use mysqli;
use App\Models\CustomerUser;
use Exception;
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
        $customer = $this->ask('Enter the customer id');
        $consoles = CustomerSubscription::where('customer_id',$customer)->where('subscription_type_id', 1)->get();
        foreach($consoles as $console){
            $database = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_DATABASE')->first();
            $user = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_USERNAME')->first();
            $password = $console->envVariables()->where('customer_subscription_id',$console->id)->where('key','DB_PASSWORD')->first();
            try{
                $mysqli = new mysqli("localhost", $user->value, $password->value, $database->value);
                $result = $mysqli->query("SELECT * FROM users");
                while($row = $result->fetch_assoc()){
                    $user = CustomerUser::updateOrCreate([
                        'email_address' => $row['email'],
                        'customer_id' => $console->customer_id,
                    ],[
                        'cellphone' => $row['cellphone'],
                        'first_name' => $row['name'],
                        'last_name' => $row['surname'],
                        'password' => $row['password']
                    ]);
                }
            }catch (Exception $e){
                echo $e->getMessage();
            }

        }
    }
}
