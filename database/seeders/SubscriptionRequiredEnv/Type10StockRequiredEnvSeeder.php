<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type10StockRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(10, PhpModuleEnvTemplate::build(
            'StockModule',
            'https://stock.example.local',
            'blackwidow_stock_management',
            true
        ));
    }
}
