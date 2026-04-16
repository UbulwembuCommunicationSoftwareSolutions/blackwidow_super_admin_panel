<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type8SslOnlyRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(8, [
            ['key' => 'SECURE_TOKEN', 'value' => 'token'],
        ]);
    }
}
