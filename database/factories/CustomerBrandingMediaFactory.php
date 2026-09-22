<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerBrandingMedia>
 */
class CustomerBrandingMediaFactory extends Factory
{
    protected $model = CustomerBrandingMedia::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => $this->faker->words(2, true),
            'checksum' => null,
        ];
    }

    /**
     * Attach a minimal PNG to the Spatie media collection.
     */
    public function withPngFile(?string $bytes = null): static
    {
        $bytes ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return $this->afterCreating(function (CustomerBrandingMedia $media) use ($bytes): void {
            $media
                ->addMediaFromString($bytes)
                ->usingFileName('branding.png')
                ->toMediaCollection('file');
            $media->refreshChecksumFromFile();
        });
    }
}
