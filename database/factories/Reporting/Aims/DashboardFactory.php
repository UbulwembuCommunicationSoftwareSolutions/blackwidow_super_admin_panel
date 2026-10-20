<?php

namespace Database\Factories\Reporting\Aims;

use App\Models\Reporting\Aims\Dashboard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dashboard>
 */
class DashboardFactory extends Factory
{
    protected $model = Dashboard::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numerify('###'),
            'description' => $this->faker->sentence(),
            'subscription_type_id' => 1,
            'handler' => Dashboard::HANDLER_AIMS_CASES,
        ];
    }
}
