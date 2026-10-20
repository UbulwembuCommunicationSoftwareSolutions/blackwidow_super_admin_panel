<?php

declare(strict_types=1);

namespace App\Models\Reporting\Aims;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as AuthUser;

class CustomerDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'dashboard_id',
        'customer_subscription_id',
        'reporting_database',
        'database_host',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'dashboard_id' => 'integer',
            'customer_subscription_id' => 'integer',
            'enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CustomerDashboard $model): void {
            if (filled($model->reporting_database) || blank($model->customer_subscription_id)) {
                return;
            }

            $database = CustomerSubscription::query()
                ->whereKey($model->customer_subscription_id)
                ->value('database_name');

            if (filled($database)) {
                $model->reporting_database = self::reportingDatabaseName((string) $database);
            }
        });
    }

    public static function reportingDatabaseName(string $aimsDatabase): string
    {
        $name = $aimsDatabase.'_rpt';

        if (strlen($name) <= 64) {
            return $name;
        }

        return substr($aimsDatabase, 0, 60).'_rpt';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function customerSubscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    public function customerUsers(): BelongsToMany
    {
        return $this->belongsToMany(CustomerUser::class, 'customer_user_customer_dashboards')
            ->withTimestamps();
    }

    /**
     * Staff with View:Dashboard may open any enabled grant. A customer admin of
     * this customer may open it. Any other customer user must be on the pivot.
     */
    public function allowsActor(?AuthUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user instanceof User) {
            return $user->can('View:Dashboard');
        }

        if (! $user instanceof CustomerUser || (int) $user->customer_id !== (int) $this->customer_id) {
            return false;
        }

        if ($user->is_system_admin) {
            return true;
        }

        return $this->customerUsers()->whereKey($user->id)->exists();
    }
}
