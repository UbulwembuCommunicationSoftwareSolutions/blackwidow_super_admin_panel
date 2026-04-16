<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type5SecurityAppRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(5, VuePwaEnvTemplate::build('Security APP'));
    }
}
