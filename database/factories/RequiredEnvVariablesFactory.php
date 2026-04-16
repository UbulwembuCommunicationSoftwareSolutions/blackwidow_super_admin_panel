<?php

namespace Database\Factories;

use App\Models\RequiredEnvVariables;
use App\Models\SubscriptionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequiredEnvVariablesFactory extends Factory
{
    protected $model = RequiredEnvVariables::class;

    public function definition(): array
    {
        return [
            'subscription_type_id' => SubscriptionType::factory(),
            'key' => strtoupper($this->faker->unique()->lexify('ENV_KEY_????')),
            'value' => $this->faker->word(),
            'requires_manual_fill' => false,
            'admin_label' => null,
            'help_text' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_manual_fill' => true,
            'value' => '',
        ]);
    }
}
