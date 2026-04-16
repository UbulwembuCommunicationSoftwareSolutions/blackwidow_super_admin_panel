<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type2FirearmRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(2, PhpModuleEnvTemplate::build(
            'FirearmDemo',
            'https://firearm.example.local',
            'blackwidow_firearm',
            true
        ));
    }
}
