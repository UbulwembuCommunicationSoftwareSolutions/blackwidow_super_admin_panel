<?php

namespace Database\Factories;

use App\Models\SubscriptionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SubscriptionTypeFactory extends Factory
{
    protected $model = SubscriptionType::class;

    public function definition(): array
    {
        return [
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'name' => $this->faker->unique()->words(2, true),
            'github_repo' => $this->faker->userName().'/'.$this->faker->slug(2),
            'branch' => 'main',
            'project_type' => 'php',
            'master_version' => null,
            'auto_promote_stable' => false,
        ];
    }
}
