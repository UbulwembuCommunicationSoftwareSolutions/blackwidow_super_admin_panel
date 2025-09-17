<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'company_name' => $this->faker->company(),
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
