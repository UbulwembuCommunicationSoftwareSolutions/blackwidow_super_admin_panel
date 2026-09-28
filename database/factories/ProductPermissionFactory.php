<?php

namespace Database\Factories;

use App\Models\ProductPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPermission>
 */
class ProductPermissionFactory extends Factory
{
    protected $model = ProductPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product' => 'console',
            'name' => fake()->randomElement(['view', 'create', 'edit', 'delete']).' '.fake()->unique()->lexify('resource_??????'),
            'guard_name' => 'web',
            'group_name' => 'General',
            'sub_group_name' => 'General',
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }

    public function forProduct(string $product): static
    {
        return $this->state(fn (): array => ['product' => $product]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
