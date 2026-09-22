<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBrandSlot>
 */
class CustomerBrandSlotFactory extends Factory
{
    protected $model = CustomerBrandSlot::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'slot' => 'login_logo',
            'customer_branding_media_id' => null,
        ];
    }

    public function withMedia(): static
    {
        return $this->state(function (array $attributes) {
            $customerId = $attributes['customer_id'] ?? Customer::factory();

            return [
                'customer_branding_media_id' => CustomerBrandingMedia::factory()->create([
                    'customer_id' => $customerId instanceof Customer ? $customerId->id : $customerId,
                ])->id,
            ];
        });
    }
}
