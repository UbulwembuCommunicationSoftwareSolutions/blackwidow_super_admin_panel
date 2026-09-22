<?php

namespace Database\Factories;

use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeRelease;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SubscriptionTypeRelease>
 */
class SubscriptionTypeReleaseFactory extends Factory
{
    protected $model = SubscriptionTypeRelease::class;

    public function definition(): array
    {
        $major = $this->faker->numberBetween(1, 5);
        $minor = $this->faker->numberBetween(0, 20);
        $patch = $this->faker->numberBetween(0, 30);

        return [
            'subscription_type_id' => SubscriptionType::factory(),
            'tag' => "v{$major}.{$minor}.{$patch}",
            'commit_sha' => $this->faker->sha1(),
            'name' => "Release v{$major}.{$minor}.{$patch}",
            'body' => $this->faker->optional()->paragraph(),
            'is_prerelease' => false,
            'is_draft' => false,
            'requires_manual_rollback' => false,
            'github_release_id' => $this->faker->optional()->numberBetween(1, 999999),
            'published_at' => Carbon::now()->subDays($this->faker->numberBetween(0, 90)),
            'synced_at' => Carbon::now(),
        ];
    }

    public function prerelease(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_prerelease' => true,
            'tag' => ($attributes['tag'] ?? 'v1.0.0').'-rc.1',
            'name' => ($attributes['name'] ?? 'Release').' RC1',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'is_draft' => true,
            'published_at' => null,
        ]);
    }
}
