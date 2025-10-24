<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CustomerSubscriptionFactory extends Factory
{
    protected $model = CustomerSubscription::class;

    public function definition(): array
    {
        return [
            'url' => $this->faker->url(),
            'domain' => $this->faker->domainName(),
            'subscription_type_id' => 1,
            'customer_id' => Customer::factory(),
            'logo_1' => $this->faker->word(),
            'logo_2' => $this->faker->word(),
            'logo_3' => $this->faker->word(),
            'env' => 'production',
            'uuid' => $this->faker->uuid(),
            'database_name' => $this->faker->word(),
            'app_name' => $this->faker->word(),
        ];
    }
}
