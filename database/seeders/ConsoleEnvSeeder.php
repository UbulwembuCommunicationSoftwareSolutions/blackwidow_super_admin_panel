<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * @deprecated Use {@see SubscriptionRequiredEnvVariablesSeeder} (seeds all subscription types). Kept for backward compatibility with existing `db:seed --class=ConsoleEnvSeeder` calls.
 */
class ConsoleEnvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(SubscriptionRequiredEnvVariablesSeeder::class);
    }
}
