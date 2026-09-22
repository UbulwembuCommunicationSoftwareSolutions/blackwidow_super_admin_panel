<?php

namespace App\Models;

use App\Jobs\PushCustomerToTenantsJob;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Jobs\SyncCustomerEnvToSubscriptionsJob;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Str;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Name of the reserved customer that catches Forge sites found with no matching subscription
     * (created directly on Forge, or predating this app's tracking) so they stay visible in
     * Filament for an operator to reassign, instead of being silently skipped.
     */
    public const PLACEHOLDER_COMPANY_NAME = 'Unassigned (Forge Sync)';

    public static function placeholder(): self
    {
        return static::query()->firstOrCreate(['company_name' => self::PLACEHOLDER_COMPANY_NAME]);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            // Your logic here
            // For example, call a method on the model
            if (strlen((string) $model->token) > 0) {
                return;
            } else {
                $model->token = Str::uuid();
                $model->uuid = Str::uuid();
                $model->save();
            }
        });

        static::created(function ($model) {
            CustomerBrandSlots::ensureDefaults($model);
        });

        static::created(function ($model) {
            PushCustomerToTenantsJob::dispatch($model->id);
        });

        static::updating(function ($model) {
            $updated = false;
            if ($model->isDirty('level_one_in_use')) {
                $updated = true;
            }
            if ($model->isDirty('level_two_in_use')) {
                $updated = true;
            }
            if ($model->isDirty('level_three_in_use')) {
                $updated = true;
            }
            if ($model->isDirty('level_one_description')) {
                $updated = true;
            }
            if ($model->isDirty('level_two_description')) {
                $updated = true;
            }
            if ($model->isDirty('level_three_description')) {
                $updated = true;
            }
            if ($model->isDirty('level_four_description')) {
                $updated = true;
            }
            if ($model->isDirty('level_five_description')) {
                $updated = true;
            }
            if ($model->isDirty('task_description')) {
                $updated = true;
            }
            if ($model->isDirty('docket_description')) {
                $updated = true;
            }
            if ($updated) {
                SendSystemConfigJob::dispatch($model->id);
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged(self::SUBSCRIPTION_ENV_FIELDS)) {
                SyncCustomerEnvToSubscriptionsJob::dispatch($model->id);
            }

            PushCustomerToTenantsJob::dispatch($model->id);
        });
    }

    /**
     * Customer-level fields that are mirrored into every subscription's .env.
     * A change to any of these re-pushes the environment to Forge.
     *
     * @var list<string>
     */
    public const SUBSCRIPTION_ENV_FIELDS = [
        'google_api_key',
        'mail_mailer',
        'mail_transport',
        'mail_host',
        'mail_url',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_scheme',
        'mail_from_address',
        'mail_from_name',
        'mail_ehlo_domain',
    ];

    protected $fillable = [
        'company_name',
        'token',
        'google_api_key',
        's3_endpoint',
        's3_key',
        's3_secret',
        's3_region',
        's3_bucket',
        's3_use_path_style_endpoint',
        'mail_mailer',
        'mail_transport',
        'mail_host',
        'mail_url',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_scheme',
        'mail_from_address',
        'mail_from_name',
        'mail_ehlo_domain',
        'max_users',
        'docket_description',
        'task_description',
        'level_one_description',
        'level_one_in_use',
        'level_two_description',
        'level_two_in_use',
        'level_three_description',
        'level_three_in_use',
        'level_four_description',
        'level_five_description',
    ];

    protected function casts(): array
    {
        return [
            's3_use_path_style_endpoint' => 'boolean',
            'mail_port' => 'integer',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_customers');
    }

    public function customerSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function customerUsers(): HasMany
    {
        return $this->hasMany(CustomerUser::class);
    }

    public function brandingMedia(): HasMany
    {
        return $this->hasMany(CustomerBrandingMedia::class);
    }

    public function brandSlots(): HasMany
    {
        return $this->hasMany(CustomerBrandSlot::class);
    }

    /**
     * The .env keys this customer controls, mapped to the values every one of its subscriptions
     * must run with (Google Maps key + SMTP credentials).
     *
     * Blank fields are omitted so the subscription type's template default is left untouched.
     * MAIL_TRANSPORT and MAIL_URL are aliases Laravel's mail config also reads, so they fall back
     * to the mailer and host rather than being left pointing at a template default.
     *
     * @return array<string, string>
     */
    public function subscriptionEnvOverrides(): array
    {
        $values = [
            'GOOGLE_MAPS_API_KEY' => $this->google_api_key,
            'MAIL_MAILER' => $this->mail_mailer,
            'MAIL_TRANSPORT' => $this->mail_transport ?: $this->mail_mailer,
            'MAIL_HOST' => $this->mail_host,
            'MAIL_URL' => $this->mail_url ?: $this->mail_host,
            'MAIL_PORT' => $this->mail_port,
            'MAIL_USERNAME' => $this->mail_username,
            'MAIL_PASSWORD' => $this->mail_password,
            'MAIL_ENCRYPTION' => $this->mail_encryption,
            'MAIL_SCHEME' => $this->mail_scheme,
            'MAIL_FROM_ADDRESS' => $this->mail_from_address,
            'MAIL_FROM_NAME' => $this->mail_from_name,
            'MAIL_EHLO_DOMAIN' => $this->mail_ehlo_domain,
        ];

        return collect($values)
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * A usable SMTP configuration needs at least a mailer, a host and a from address.
     */
    public function hasMailConfiguration(): bool
    {
        return filled($this->mail_mailer) && filled($this->mail_host) && filled($this->mail_from_address);
    }

    /**
     * Laravel s3 disk settings for S3-compatible storage (e.g. MinIO). See config/filesystems.php s3 + endpoint + use_path_style_endpoint.
     *
     * @return array<string, mixed>|null
     */
    public function s3DiskConfiguration(): ?array
    {
        if (! filled($this->s3_endpoint) || ! filled($this->s3_key) || ! filled($this->s3_secret) || ! filled($this->s3_bucket)) {
            return null;
        }

        return [
            'driver' => 's3',
            'key' => $this->s3_key,
            'secret' => $this->s3_secret,
            'region' => $this->s3_region !== null && $this->s3_region !== '' ? $this->s3_region : 'us-east-1',
            'bucket' => $this->s3_bucket,
            'endpoint' => $this->s3_endpoint,
            'use_path_style_endpoint' => (bool) $this->s3_use_path_style_endpoint,
        ];
    }
}
