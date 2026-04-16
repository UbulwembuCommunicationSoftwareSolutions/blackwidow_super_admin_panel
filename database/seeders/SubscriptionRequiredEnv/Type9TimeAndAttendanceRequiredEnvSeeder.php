<?php

namespace Database\Seeders\SubscriptionRequiredEnv;

use Illuminate\Database\Seeder;

class Type9TimeAndAttendanceRequiredEnvSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionRequiredEnvRecords::sync(9, PhpModuleEnvTemplate::build(
            'TimeAndAttendance',
            'https://time-and-attendance.example.local',
            'blackwidow_time_and_attendance',
            true
        ));
    }
}
