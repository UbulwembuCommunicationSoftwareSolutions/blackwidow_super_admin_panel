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
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'app_url' => $this->faker->url(),
            'console_login_logo' => $this->faker->word(),
            'console_menu_logo' => $this->faker->word(),
            'console_background_logo' => $this->faker->word(),
            'app_install_logo' => $this->faker->word(),
            'app_background_logo' => $this->faker->word(),

            'customer_id' => Customer::factory(),
            'subscription_type_id' => SubscriptionType::factory(),
        ];
    }
}
