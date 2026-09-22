<?php

namespace Database\Factories;

use App\Models\CustomerBrandingMedia;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSubscriptionBrandSlot>
 */
class CustomerSubscriptionBrandSlotFactory extends Factory
{
    protected $model = CustomerSubscriptionBrandSlot::class;

    public function definition(): array
    {
        return [
            'customer_subscription_id' => CustomerSubscription::factory(),
            'slot' => 'login_logo',
            'customer_branding_media_id' => null,
            'is_override' => true,
            'cleared' => false,
        ];
    }

    public function clearedOverride(): static
    {
        return $this->state(fn () => [
            'is_override' => true,
            'cleared' => true,
            'customer_branding_media_id' => null,
        ]);
    }

    public function withMedia(): static
    {
        return $this->state(function (array $attributes) {
            $subscription = CustomerSubscription::query()->find($attributes['customer_subscription_id'])
                ?? CustomerSubscription::factory()->create();

            return [
                'customer_subscription_id' => $subscription->id,
                'customer_branding_media_id' => CustomerBrandingMedia::factory()->create([
                    'customer_id' => $subscription->customer_id,
                ])->id,
                'is_override' => true,
                'cleared' => false,
            ];
        });
    }
}
