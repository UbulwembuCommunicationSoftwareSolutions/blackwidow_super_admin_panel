<?php

namespace App\Models;

use App\Jobs\PushBrandingToTenantsJob;
use App\Services\LogoSyncService;
use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class CustomerSubscription extends Model
{
    use HasFactory;

    /**
     * MySQL account names (user part) are limited to 32 characters. Forge enforces the same.
     */
    public const MYSQL_USER_NAME_MAX_LENGTH = 32;

    /** Subscription logo slots, in the order the form shows them. */
    public const LOGO_SLOTS = ['logo_1', 'logo_2', 'logo_3', 'logo_4', 'logo_5'];

    /**
     * When true, model hooks must not queue an outbound branding push (inbound apply).
     */
    public bool $skipSync = false;

    protected $hidden = [
        'database_password',
    ];

    /**
     * Public URLs for whichever logo slots are filled. The columns store disk
     * paths, which are useless to an API client on another host. Cast to an
     * object so the JSON shape stays a map even when nothing is uploaded.
     *
     * @return Attribute<stdClass, never>
     */
    protected function logoUrls(): Attribute
    {
        return Attribute::make(get: function (): stdClass {
            $disk = Storage::disk('public');
            $urls = [];

            foreach (self::LOGO_SLOTS as $slot) {
                $path = $this->getAttribute($slot);
                if (filled($path)) {
                    $urls[$slot] = $disk->url($path);
                }
            }

            return (object) $urls;
        });
    }

    protected $fillable = [
        'url',
        'domain',
        'subscription_type_id',
        'server_id',
        'customer_id',
        'logo_1',
        'logo_2',
        'logo_3',
        'env',
        'uuid',
        'forge_site_id',
        'logo_4',
        'logo_5',
        'logo_1_updated_at',
        'logo_2_updated_at',
        'logo_3_updated_at',
        'logo_4_updated_at',
        'logo_5_updated_at',
        'logo_1_checksum',
        'logo_2_checksum',
        'logo_3_checksum',
        'created_at',
        'updated_at',
        'database_name',
        'database_user',
        'app_name',
        'env',
        'site_created_at',
        'github_sent_at',
        'env_sent_at',
        'deployment_script_sent_at',
        'ssl_deployed_at',
        'deployed_at',
        'panic_button_enabled',
        'deployed_version',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerSubscription $model): void {
            $type = $model->relationLoaded('subscriptionType')
                ? $model->subscriptionType
                : SubscriptionType::query()->find($model->subscription_type_id);
            if (! $type) {
                return;
            }
            if (strtolower((string) $type->project_type) !== 'php') {
                return;
            }
            if (! filled($model->database_name)) {
                return;
            }
            if (blank($model->database_password)) {
                // symbols: false -- Forge's database user password validation rejects some characters
                // in Str::password()'s default symbol pool (e.g. backtick), which would otherwise
                // cause a random, hard-to-reproduce failure whenever the generator happened to draw one.
                $model->database_password = Str::password(32, symbols: false);
            }
            if (blank($model->database_user)) {
                $model->database_user = self::limitMysqlUserName(
                    self::normalizeDatabaseIdentifier((string) $model->database_name)
                );
            }
        });

        static::updated(function (CustomerSubscription $model): void {
            if ($model->skipSync) {
                return;
            }

            $changedSlots = [];
            foreach (self::LOGO_SLOTS as $slot) {
                if ($model->wasChanged($slot)) {
                    $changedSlots[] = $slot;
                }
            }

            if ($changedSlots === []) {
                return;
            }

            // Stamp timestamps for any path-only change that did not already set them.
            $needsStamp = [];
            foreach ($changedSlots as $slot) {
                if (! $model->wasChanged($slot.'_updated_at')) {
                    $needsStamp[] = $slot;
                }
            }

            if ($needsStamp !== []) {
                LogoSyncService::stampSlots($model, $needsStamp, pushToCms: false);
            }

            $cmsSlots = [];
            foreach ($changedSlots as $saSlot) {
                $cmsSlot = array_search($saSlot, BrandingSyncPayload::CMS_TO_SA_SLOT, true);
                if ($cmsSlot !== false) {
                    $cmsSlots[] = $cmsSlot;
                }
            }

            if ($cmsSlots !== [] && (int) $model->subscription_type_id === 1) {
                PushBrandingToTenantsJob::dispatch($model->id, $cmsSlots);
            }
        });
    }

    public function brandingSyncLogs(): HasMany
    {
        return $this->hasMany(BrandingSyncLog::class);
    }

    /**
     * Queue an outbound branding push for the given CMS slots (login_logo, ...).
     *
     * @param  list<string>  $cmsSlots
     */
    public function queueBrandingPush(array $cmsSlots): void
    {
        if ($this->skipSync) {
            return;
        }

        if ((int) $this->subscription_type_id !== 1) {
            return;
        }

        $cmsSlots = array_values(array_intersect($cmsSlots, BrandingSyncPayload::SLOTS));
        if ($cmsSlots === []) {
            return;
        }

        PushBrandingToTenantsJob::dispatch($this->id, $cmsSlots);
    }

    protected $casts = [
        'site_deployment_queue_started_at' => 'datetime',
        'last_deployment_error_at' => 'datetime',
        'logo_1_updated_at' => 'datetime',
        'logo_2_updated_at' => 'datetime',
        'logo_3_updated_at' => 'datetime',
        'logo_4_updated_at' => 'datetime',
        'logo_5_updated_at' => 'datetime',
    ];

    public $appends = ['null_variable_count', 'logo_urls'];

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public function deploymentScript()
    {
        return $this->hasMany(DeploymentScript::class);
    }

    public function deploymentJobs(): HasMany
    {
        return $this->hasMany(CustomerSubscriptionDeploymentJob::class);
    }

    public static function createMissingEnv()
    {
        $subscriptions = CustomerSubscription::get();
        foreach ($subscriptions as $subscription) {
            $envs = $subscription->envVariables;
            $requiredEnv = TemplateEnvVariables::where('subscription_type_id', $subscription->subscription_type_id)->get();
            foreach ($requiredEnv as $env) {
                $found = false;
                foreach ($envs as $e) {
                    if ($e->key == $env->key) {
                        echo $e->key.' found in '.$subscription->id."\n";
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    echo $env->key.' not found in '.$subscription->id."\n";
                    $newEnv = new EnvVariables;
                    $newEnv->key = $env->key;
                    $newEnv->value = $env->initialEnvValue();
                    $newEnv->customer_subscription_id = $subscription->id;
                    $newEnv->save();
                }
            }
        }
    }

    public function envVariables()
    {
        return $this->hasMany(EnvVariables::class);
    }

    public function getNullVariableCountAttribute(): int
    {
        $manualKeys = TemplateEnvVariables::query()
            ->where('subscription_type_id', $this->subscription_type_id)
            ->where('requires_manual_fill', true)
            ->pluck('key');

        if ($manualKeys->isEmpty()) {
            return 0;
        }

        return $this->envVariables()
            ->whereIn('key', $manualKeys)
            ->where(function ($query) {
                $query->whereNull('value')->orWhere('value', '');
            })
            ->count();
    }

    /**
     * True when any manual-required env key for this subscription type is still null or empty.
     */
    public function hasIncompleteManualEnvVariables(): bool
    {
        return $this->getNullVariableCountAttribute() > 0;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Same rules as Filament customer subscription form (MySQL database / user naming).
     */
    public static function normalizeDatabaseIdentifier(string $databaseName): string
    {
        $databaseName = str_replace(' ', '_', $databaseName);
        $databaseName = str_replace('-', '_', $databaseName);
        $databaseName = str_replace('.', '_', $databaseName);
        $databaseName = str_replace('/', '_', $databaseName);
        $databaseName = str_replace('\\', '_', $databaseName);
        $databaseName = str_replace('|', '_', $databaseName);
        $databaseName = str_replace(';', '_', $databaseName);
        $databaseName = str_replace(':', '_', $databaseName);
        $databaseName = str_replace('"', '_', $databaseName);
        $databaseName = str_replace('\'', '_', $databaseName);
        $databaseName = str_replace('`', '_', $databaseName);
        $databaseName = str_replace('~', '_', $databaseName);
        $databaseName = str_replace('!', '_', $databaseName);
        $databaseName = str_replace('@', '_', $databaseName);
        $databaseName = str_replace('#', '_', $databaseName);
        $databaseName = str_replace('$', '_', $databaseName);
        $databaseName = str_replace('%', '_', $databaseName);
        $databaseName = str_replace('^', '_', $databaseName);
        $databaseName = str_replace('&', '_', $databaseName);
        $databaseName = str_replace('*', '_', $databaseName);
        $databaseName = str_replace('(', '_', $databaseName);
        $databaseName = str_replace(')', '_', $databaseName);
        $databaseName = str_replace('=', '_', $databaseName);
        $databaseName = str_replace('+', '_', $databaseName);
        $databaseName = str_replace('[', '_', $databaseName);
        $databaseName = str_replace(']', '_', $databaseName);
        $databaseName = str_replace('{', '_', $databaseName);
        $databaseName = str_replace('}', '_', $databaseName);
        $databaseName = str_replace('<', '_', $databaseName);
        $databaseName = str_replace('>', '_', $databaseName);
        $databaseName = str_replace(',', '_', $databaseName);
        $databaseName = str_replace('?', '_', $databaseName);

        return $databaseName;
    }

    public function forgeMysqlIdentifier(): string
    {
        return self::normalizeDatabaseIdentifier((string) $this->database_name);
    }

    /**
     * Truncate a MySQL user name to {@see MYSQL_USER_NAME_MAX_LENGTH} (MySQL + Forge limit).
     */
    public static function limitMysqlUserName(string $name): string
    {
        if ($name === '') {
            return $name;
        }
        if (mb_strlen($name) <= self::MYSQL_USER_NAME_MAX_LENGTH) {
            return $name;
        }

        return mb_substr($name, 0, self::MYSQL_USER_NAME_MAX_LENGTH);
    }

    /**
     * MySQL user for Forge + DB_USERNAME. Defaults to the normalized database name when not set.
     */
    public function forgeMysqlUser(): string
    {
        if (filled($this->database_user)) {
            return self::limitMysqlUserName(self::normalizeDatabaseIdentifier((string) $this->database_user));
        }

        return self::limitMysqlUserName($this->forgeMysqlIdentifier());
    }

    public function isPhpSubscriptionWithDatabase(): bool
    {
        if (! filled($this->database_name)) {
            return false;
        }
        $type = $this->relationLoaded('subscriptionType')
            ? $this->subscriptionType
            : SubscriptionType::query()->find($this->subscription_type_id);

        return $type && strtolower((string) $type->project_type) === 'php';
    }

    /**
     * Ensure a stored password exists for Forge MySQL user creation and env sync (legacy rows).
     */
    public function ensureDatabasePasswordForForge(): void
    {
        if (! $this->isPhpSubscriptionWithDatabase()) {
            return;
        }
        if (filled($this->database_password)) {
            return;
        }
        $this->forceFill(['database_password' => Str::password(32, symbols: false)])->save();
    }

    /**
     * Backfill MySQL user for older rows (matches default: same as database name, normalized).
     */
    public function ensureDatabaseUserForForge(): void
    {
        if (! $this->isPhpSubscriptionWithDatabase()) {
            return;
        }
        if (! filled($this->database_user)) {
            $this->forceFill(['database_user' => self::limitMysqlUserName($this->forgeMysqlIdentifier())])->save();

            return;
        }
        $limited = self::limitMysqlUserName(self::normalizeDatabaseIdentifier((string) $this->database_user));
        if ((string) $this->database_user !== $limited) {
            $this->forceFill(['database_user' => $limited])->save();
        }
    }
}
