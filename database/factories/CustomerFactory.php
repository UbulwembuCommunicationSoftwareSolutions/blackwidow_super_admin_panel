<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'company_name' => $this->faker->company(),
            'google_api_key' => null,
            's3_endpoint' => null,
            's3_key' => null,
            's3_secret' => null,
            's3_region' => null,
            's3_bucket' => null,
            's3_use_path_style_endpoint' => true,
            'token' => $this->faker->uuid(),
            'max_users' => $this->faker->numberBetween(1, 100),
            'docket_description' => $this->faker->sentence(),
            'task_description' => $this->faker->sentence(),
            'level_one_description' => $this->faker->sentence(),
            'level_one_in_use' => $this->faker->boolean(),
            'level_two_description' => $this->faker->sentence(),
            'level_two_in_use' => $this->faker->boolean(),
            'level_three_description' => $this->faker->sentence(),
            'level_three_in_use' => $this->faker->boolean(),
            'level_four_description' => $this->faker->sentence(),
            'level_five_description' => $this->faker->sentence(),
        ];
    }
}
