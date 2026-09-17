<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        return [
            'company_name' => $this->faker->company(),
            'google_api_key' => null,
            's3_endpoint' => null,
            's3_key' => null,
            's3_secret' => null,
            's3_region' => null,
            's3_bucket' => null,
            's3_use_path_style_endpoint' => true,
            'mail_mailer' => null,
            'mail_transport' => null,
            'mail_host' => null,
            'mail_url' => null,
            'mail_port' => null,
            'mail_username' => null,
            'mail_password' => null,
            'mail_encryption' => null,
            'mail_scheme' => null,
            'mail_from_address' => null,
            'mail_from_name' => null,
            'mail_ehlo_domain' => null,
            'token' => $this->faker->uuid(),
            'max_users' => $this->faker->numberBetween(1, 100),
            'docket_description' => $this->faker->sentence(),
            'task_description' => $this->faker->sentence(),
            'level_one_description' => $this->faker->sentence(),
            'level_one_in_use' => $this->faker->boolean(),
            'level_two_description' => $this->faker->sentence(),
            'level_two_in_use' => $this->faker->boolean(),
            'level_three_description' => $this->faker->sentence(),
            'level_three_in_use' => $this->faker->boolean(),
            'level_four_description' => $this->faker->sentence(),
            'level_five_description' => $this->faker->sentence(),
        ];
    }

    /**
     * A customer whose SMTP settings are filled, so every subscription env gets the MAIL_* overrides.
     */
    public function withMailSettings(): static
    {
        return $this->state(fn () => [
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.blackwidow.org.za',
            'mail_port' => 465,
            'mail_username' => 'demo@blackwidow.org.za',
            'mail_password' => 'Spider1962$#@!',
            'mail_encryption' => 'null',
            'mail_from_address' => 'demo@blackwidow.org.za',
            'mail_ehlo_domain' => 'blackwidow.org.za',
        ]);
    }
}
