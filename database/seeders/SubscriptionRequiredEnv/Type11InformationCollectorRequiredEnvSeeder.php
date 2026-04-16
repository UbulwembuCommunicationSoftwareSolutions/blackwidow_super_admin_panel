<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type11InformationCollectorRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(11, PhpModuleEnvTemplate::build(
            'InformationCollector',
            'https://information-collector.example.local',
            'blackwidow_information_collector',
            true
        ));
    }
}
