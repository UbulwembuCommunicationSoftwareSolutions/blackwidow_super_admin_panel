<?php

namespace App\Support\BrandingSync;

use App\Models\Customer;
use App\Models\CustomerBrandSlot;

final class CustomerBrandSlots
{
    public static function ensureDefaults(Customer $customer): void
    {
        foreach (BrandingSyncPayload::SLOTS as $slot) {
            CustomerBrandSlot::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'slot' => $slot,
            ]);
        }
    }
}
