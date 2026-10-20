<?php

namespace Database\Factories\Reporting\Aims;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Reporting\Aims\CustomerDashboard;
use App\Models\Reporting\Aims\Dashboard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDashboard>
 */
class CustomerDashboardFactory extends Factory
{
    protected $model = CustomerDashboard::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'dashboard_id' => Dashboard::factory(),
            'customer_subscription_id' => CustomerSubscription::factory(),
            'reporting_database' => 'aims_rpt_test',
            'database_host' => null,
            'database_time_zone' => null,
            'enabled' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CustomerDashboard $dashboard): void {
            $subscription = CustomerSubscription::query()->find($dashboard->customer_subscription_id);

            if ($subscription && (int) $subscription->customer_id !== (int) $dashboard->customer_id) {
                $subscription->forceFill(['customer_id' => $dashboard->customer_id])->save();
            }
        });
    }
}
