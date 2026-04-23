<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerSubscription extends Model
{
    use HasFactory;

    protected $hidden = [
        'database_password',
    ];

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
                $model->database_password = Str::password(32);
            }
            if (blank($model->database_user)) {
                $model->database_user = self::normalizeDatabaseIdentifier((string) $model->database_name);
            }
        });
    }

    protected $casts = [
        'site_deployment_queue_started_at' => 'datetime',
        'last_deployment_error_at' => 'datetime',
    ];

    public $appends = ['null_variable_count'];

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public function deploymentScript()
    {
        return $this->hasMany(DeploymentScript::class);
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
     * MySQL user for Forge + DB_USERNAME. Defaults to the normalized database name when not set.
     */
    public function forgeMysqlUser(): string
    {
        if (filled($this->database_user)) {
            return self::normalizeDatabaseIdentifier((string) $this->database_user);
        }

        return $this->forgeMysqlIdentifier();
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
        $this->forceFill(['database_password' => Str::password(32)])->save();
    }

    /**
     * Backfill MySQL user for older rows (matches default: same as database name, normalized).
     */
    public function ensureDatabaseUserForForge(): void
    {
        if (! $this->isPhpSubscriptionWithDatabase()) {
            return;
        }
        if (filled($this->database_user)) {
            return;
        }
        $this->forceFill(['database_user' => $this->forgeMysqlIdentifier()])->save();
    }
}
