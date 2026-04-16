<?php

namespace Database\Seeders;

use Database\Seeders\SubscriptionRequiredEnv\Type10StockRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type11InformationCollectorRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type1ConsoleRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type2FirearmRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type3ResponderAppRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type4ReporterAppRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type5SecurityAppRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type6DriverAppRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type7SurveyAppRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type8SslOnlyRequiredEnvSeeder;
use Database\Seeders\SubscriptionRequiredEnv\Type9TimeAndAttendanceRequiredEnvSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionRequiredEnvVariablesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                Type1ConsoleRequiredEnvSeeder::class,
                Type2FirearmRequiredEnvSeeder::class,
                Type3ResponderAppRequiredEnvSeeder::class,
                Type4ReporterAppRequiredEnvSeeder::class,
                Type5SecurityAppRequiredEnvSeeder::class,
                Type6DriverAppRequiredEnvSeeder::class,
                Type7SurveyAppRequiredEnvSeeder::class,
                Type8SslOnlyRequiredEnvSeeder::class,
                Type9TimeAndAttendanceRequiredEnvSeeder::class,
                Type10StockRequiredEnvSeeder::class,
                Type11InformationCollectorRequiredEnvSeeder::class,
            ]);
        });
    }
}
