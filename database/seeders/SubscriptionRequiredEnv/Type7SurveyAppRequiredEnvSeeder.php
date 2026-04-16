<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type7SurveyAppRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(7, VuePwaEnvTemplate::build('Survey APP'));
    }
}
