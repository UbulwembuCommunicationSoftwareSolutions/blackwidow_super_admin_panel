<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerUser>
 */
class CustomerUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => 1,
            'super_admin_user_id' => $this->faker->unique()->uuid(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email_address' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'cellphone' => $this->faker->phoneNumber(),
            'console_access' => $this->faker->boolean(),
            'firearm_access' => $this->faker->boolean(),
            'responder_access' => $this->faker->boolean(),
            'reporter_access' => $this->faker->boolean(),
            'security_access' => $this->faker->boolean(),
            'driver_access' => $this->faker->boolean(),
            'survey_access' => $this->faker->boolean(),
            'time_and_attendance_access' => $this->faker->boolean(),
            'stock_access' => $this->faker->boolean(),
            'is_system_admin' => $this->faker->boolean(20), // 20% chance
            'skip_sync' => false,
            'last_synced_at' => null,
            'sync_hash' => null,
        ];
    }
}
