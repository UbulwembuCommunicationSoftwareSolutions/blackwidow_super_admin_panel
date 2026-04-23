<?php

namespace App\Helpers;

use App\Jobs\GetSitesForServerJob;
use App\Jobs\TriggerForgeDeployment;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\ForgeServer;
use App\Models\TemplateEnvVariables;
use Exception;
use Laravel\Forge\Exceptions\ValidationException;
use Laravel\Forge\Forge;
use Log;
use Throwable;

class ForgeApi
{
    public $forge;

    public $servers;

    public $sites;

    public function __construct()
    {
        $apiKey = config('services.forge.key');

        if (blank($apiKey)) {
            throw new \RuntimeException(
                'Laravel Forge is not configured: set FORGE_API_KEY in your .env and ensure config is not cached with a missing value (php artisan config:clear then config:cache after setting the key).'
            );
        }

        $this->forge = new Forge($apiKey);
    }

    public function sendCommand($customerSubscriptionId, $command)
    {
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        if (! $customerSubscription) {
            throw new \InvalidArgumentException('Customer subscription not found: '.$customerSubscriptionId);
        }
        $this->assertForgeSiteReady($customerSubscription);
        $commands_array['command'] = $command;
        Log::info('forge.execute_site_command', [
            'customer_subscription_id' => (int) $customerSubscriptionId,
        ]);
        $this->forge->executeSiteCommand($customerSubscription->server_id, $customerSubscription->forge_site_id, $commands_array);
    }

    public function horizonCreator($customerSubscription)
    {
        $data = [
            'command' => 'php /home/forge/'.$customerSubscription->domain.'/artisan horizon',
        ];
        $this->forge->
        $this->forge->createDaemon($customerSubscription->server_id, $data);
    }

    public function sendDeploymentScript(CustomerSubscription $customerSubscription)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $this->forge->updateSiteDeploymentScript($customerSubscription->server_id, $customerSubscription->forge_site_id, $customerSubscription->deploymentScript()->first()->script);
    }

    public function sendGitRepository($customerSubscription)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $this->forge->installGitRepositoryOnSite(
            $customerSubscription->server_id,
            $customerSubscription->forge_site_id,
            [
                'provider' => 'github',
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'branch' => $customerSubscription->subscriptionType->branch,
            ]
        );
    }

    public function syncForge()
    {
        $servers = ForgeServer::get();
        foreach ($servers as $server) {
            echo 'Syncing Server: '.$server->name.' with ID of : '.$server->forge_server_id." \n";
            GetSitesForServerJob::dispatch($server->forge_server_id);
        }
    }

    public function getSitesForServer($serverId)
    {
        $sites = $this->getSites($serverId);
        if (! is_array($sites)) {
            return;
        }
        foreach ($sites as $site) {
            $customerSubscription = CustomerSubscription::query()
                ->where('server_id', $serverId)
                ->where(function ($q) use ($site) {
                    $q->where('domain', $site->name)
                        ->orWhere('url', 'like', '%://'.$site->name.'%');
                })
                ->first();
            if ($customerSubscription) {
                $customerSubscription->forge_site_id = $site->id;
                $customerSubscription->save();
                Log::info('forge.site_matched', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'forge_site_id' => $site->id,
                    'site_name' => $site->name,
                ]);
            }
        }
    }

    /**
     * Ensure this subscription has forge_site_id by re-fetching Forge sites for its server (used when API id was not saved).
     */
    public function tryLinkForgeSiteId(CustomerSubscription $customerSubscription): bool
    {
        if ($customerSubscription->forge_site_id) {
            return true;
        }
        if (! $customerSubscription->server_id) {
            Log::warning('forge.try_link_no_server', ['customer_subscription_id' => $customerSubscription->id]);

            return false;
        }
        $this->getSitesForServer($customerSubscription->server_id);
        $customerSubscription->refresh();

        return (bool) $customerSubscription->forge_site_id;
    }

    public function assertForgeSiteReady(CustomerSubscription $customerSubscription): CustomerSubscription
    {
        $fresh = $customerSubscription->fresh() ?? $customerSubscription;
        if (! $fresh->server_id || ! $fresh->forge_site_id) {
            throw new \RuntimeException(
                'Subscription '.$fresh->id.' is not ready for Forge API calls (missing server_id or forge_site_id).'
            );
        }

        return $fresh;
    }

    public function deployAllConsoles()
    {
        $customerSubscriptions = CustomerSubscription::where('subscription_type_id', 1)->get();
        foreach ($customerSubscriptions as $customerSubscription) {
            if ($customerSubscription->server_id == null || $customerSubscription->forge_site_id == null) {
                Log::error('Server ID or Site ID not found for Subscription ID: '.$customerSubscription->id);
            } else {
                TriggerForgeDeployment::dispatch($customerSubscription->server_id, $customerSubscription->forge_site_id);
            }
        }
    }

    public function parseEnvContent($content)
    {
        $lines = explode("\n", $content);
        $env = [];

        foreach ($lines as $line) {
            if (empty($line) || strpos(trim($line), '#') === 0) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            }
            $env[$key] = $value;
        }

        return $env;
    }

    public function getServers()
    {
        $this->servers = $this->forge->servers();

        return $this->servers;
    }

    public function getSites($serverId)
    {
        $sites = [];
        try {
            foreach ($this->forge->sites($serverId) as $site) {
                $sites[] = $site;
            }

            return $sites;
        } catch (Exception $e) {
            Log::error('forge.get_sites_failed', [
                'server_id' => $serverId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function deploySite($server_id, $site_id)
    {
        $this->forge->deploySite($server_id, $site_id);
    }

    /**
     * Request a Let's Encrypt certificate on Forge. By default the Forge API is called with
     * wait=false (request accepted, installation may still be in progress) to avoid job timeouts
     * and queue retries that re-trigger LE and hit rate limits.
     *
     * @param  bool  $waitUntilInstalled  When true, blocks until the SDK reports the certificate is installed.
     */
    public function letsEncryptCertificate(CustomerSubscription $customerSubscription, bool $waitUntilInstalled = false)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $domain = str_replace('http://', '', $customerSubscription->url);
        $domain = str_replace('https://', '', $domain);
        $this->forge->obtainLetsEncryptCertificate(
            $customerSubscription->server_id,
            $customerSubscription->forge_site_id,
            [
                'domains' => [$domain],
                'wildcard' => false,
            ],
            $waitUntilInstalled
        );

        Log::info('forge.letsencrypt_requested', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $customerSubscription->server_id,
            'forge_site_id' => $customerSubscription->forge_site_id,
            'domain' => $domain,
            'wait_until_installed' => $waitUntilInstalled,
            'note' => $waitUntilInstalled
                ? null
                : 'Certificate may still be installing on Forge; check Forge if HTTPS is not live yet.',
        ]);
    }

    /**
     * Ensure MySQL user, password, and server database on Forge (php + DB subscriptions only).
     */
    public function prepareForgeServerDatabaseForSite(int $server_id, CustomerSubscription $customerSubscription): void
    {
        if (! $this->needsForgeServerDatabase($customerSubscription)) {
            return;
        }
        $customerSubscription->loadMissing('subscriptionType');
        $this->provisionForgeServerDatabase($server_id, $customerSubscription);
        $this->provisionForgeServerDatabaseUser($server_id, $customerSubscription);
    }

    /**
     * Ensure MySQL user name + password are set; used before {@see provisionForgeServerDatabaseUser}.
     */
    public function prepareForgeServerDatabaseUserCredentials(CustomerSubscription $customerSubscription): void
    {
        if (! $this->needsForgeServerDatabase($customerSubscription)) {
            return;
        }
        $customerSubscription->loadMissing('subscriptionType');
        $customerSubscription->ensureDatabaseUserForForge();
        $customerSubscription->ensureDatabasePasswordForForge();
        $customerSubscription->refresh();
    }

    /**
     * @param  bool  $skipDatabaseProvisioning  Set true when {@see prepareForgeServerDatabaseForSite} already ran in the same deployment batch.
     */
    public function createSite($server_id, CustomerSubscription $customerSubscription, bool $skipDatabaseProvisioning = false)
    {
        $customerSubscription->loadMissing('subscriptionType');

        $this->addMissingEnv($customerSubscription);
        $customerSubscription->refresh();

        if (! $skipDatabaseProvisioning) {
            $this->prepareForgeServerDatabaseForSite($server_id, $customerSubscription);
        }

        $useForgeSiteDatabase = $this->needsForgeServerDatabase($customerSubscription);
        $databaseName = $useForgeSiteDatabase ? $customerSubscription->forgeMysqlIdentifier() : null;

        if ($databaseName) {
            $payload = [
                'domain' => $customerSubscription->domain,
                'project_type' => $customerSubscription->subscriptionType->project_type,
                'directory' => $customerSubscription->subscriptionType->public_dir,
                'php_version' => 'php83',
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'repository_provider' => 'github',
                'repository_branch' => $customerSubscription->subscriptionType->branch,
                'database' => $databaseName,
            ];
        } else {
            $payload = [
                'domain' => $customerSubscription->domain,
                'project_type' => $customerSubscription->subscriptionType->project_type,
                'directory' => $customerSubscription->subscriptionType->public_dir,
                'php_version' => 'php83',
                'nginx_template' => $customerSubscription->subscriptionType->nginx_template_id,
                'repository' => $customerSubscription->subscriptionType->github_repo,
                'repository_provider' => 'github',
                'repository_branch' => $customerSubscription->subscriptionType->branch,
            ];
        }

        Log::info('forge.create_site', [
            'customer_subscription_id' => $customerSubscription->id,
            'domain' => $customerSubscription->domain,
        ]);
        $site = $this->forge->createSite($server_id, $payload);
        $customerSubscription->forge_site_id = (string) $site->id;
        if (! $customerSubscription->site_created_at) {
            $customerSubscription->site_created_at = now();
        }
        $customerSubscription->save();
        Log::info('forge.site_created', [
            'customer_subscription_id' => $customerSubscription->id,
            'forge_site_id' => $site->id,
        ]);
        $this->syncForge();
    }

    public function needsForgeServerDatabase(CustomerSubscription $customerSubscription): bool
    {
        $customerSubscription->loadMissing('subscriptionType');

        return $customerSubscription->isPhpSubscriptionWithDatabase();
    }

    /**
     * Create the MySQL database on Forge (no user). {@see provisionForgeServerDatabaseUser} for the database user.
     */
    public function provisionForgeServerDatabase(int $server_id, CustomerSubscription $customerSubscription): void
    {
        $name = $customerSubscription->forgeMysqlIdentifier();
        if ($name === '') {
            Log::warning('forge.create_database.skip_missing_prereq', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database_empty' => true,
            ]);

            return;
        }

        $databasePayload = [
            'name' => $name,
        ];

        Log::info('forge.create_database', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $server_id,
            'database' => $name,
        ]);

        try {
            $createdDatabase = $this->forge->createDatabase($server_id, $databasePayload);
            $databaseId = (int) $createdDatabase->id;
            Log::info('forge.database_created', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
            ]);
        } catch (ValidationException $e) {
            $validationMessage = $e->getMessage();
            $databaseId = $this->resolveForgeDatabaseId($server_id, $name);
            if ($databaseId !== null) {
                Log::info('forge.create_database.skip_exists', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'server_id' => $server_id,
                    'database' => $name,
                    'database_id' => $databaseId,
                    'validation_message' => $validationMessage,
                    'forge_validation_errors' => $e->errors(),
                ]);
            } else {
                Log::warning('forge.create_database.validation_not_recovered', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'server_id' => $server_id,
                    'database' => $name,
                    'validation_message' => $validationMessage,
                    'forge_validation_errors' => $e->errors(),
                ]);
                throw $e;
            }
        } catch (Throwable $e) {
            Log::error('forge.create_database.api_failed', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create the MySQL user on Forge and grant access to the subscription database.
     */
    public function provisionForgeServerDatabaseUser(int $server_id, CustomerSubscription $customerSubscription): void
    {
        $this->prepareForgeServerDatabaseUserCredentials($customerSubscription);

        $name = $customerSubscription->forgeMysqlIdentifier();
        $user = $customerSubscription->forgeMysqlUser();
        $password = (string) $customerSubscription->database_password;
        if ($password === '') {
            $customerSubscription->ensureDatabasePasswordForForge();
            $customerSubscription->refresh();
            $password = (string) $customerSubscription->database_password;
        }
        if ($name === '' || $user === '' || $password === '') {
            Log::warning('forge.create_database_user.skip_missing_prereq', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database_empty' => $name === '',
                'user_empty' => $user === '',
                'password_empty' => $password === '',
            ]);

            return;
        }

        $databaseId = $this->resolveForgeDatabaseId($server_id, $name);
        if ($databaseId === null) {
            $message = 'Forge MySQL database "'.$name.'" was not found on the server. Create the database step must succeed first.';
            Log::error('forge.create_database_user.missing_database', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
            ]);
            throw new \RuntimeException($message);
        }

        $userPayload = [
            'name' => $user,
            'password' => $password,
            'databases' => [$databaseId],
        ];

        Log::info('forge.create_database_user', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $server_id,
            'database' => $name,
            'database_id' => $databaseId,
            'mysql_user' => $user,
            'mysql_user_length' => strlen($user),
            'password_length' => strlen($password),
        ]);

        try {
            $this->forge->createDatabaseUser($server_id, $userPayload);
            Log::info('forge.database_user_created', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
                'mysql_user' => $user,
            ]);
            $customerSubscription->refresh();
            $this->syncMysqlEnvFromSubscription($customerSubscription);
        } catch (ValidationException $e) {
            $validationMessage = $e->getMessage();
            if ($this->forgeDatabaseUserNameExistsOnServer($server_id, $user)
                || $this->isLikelyDuplicateDatabaseUserMessage($validationMessage)) {
                Log::warning('forge.create_database_user.skip_exists', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'server_id' => $server_id,
                    'database' => $name,
                    'database_id' => $databaseId,
                    'mysql_user' => $user,
                    'validation_message' => $validationMessage,
                    'forge_validation_errors' => $e->errors(),
                ]);
                $customerSubscription->refresh();
                $this->syncMysqlEnvFromSubscription($customerSubscription);

                return;
            }

            Log::warning('forge.create_database_user.validation_not_recovered', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
                'mysql_user' => $user,
                'validation_message' => $validationMessage,
                'forge_validation_errors' => $e->errors(),
            ]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('forge.create_database_user.api_failed', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
                'mysql_user' => $user,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Heuristic: Forge may return a validation error when the MySQL user name is already taken.
     */
    protected function isLikelyDuplicateDatabaseUserMessage(string $message): bool
    {
        $m = strtolower($message);
        if (! str_contains($m, 'user')) {
            return false;
        }

        return str_contains($m, 'exist')
            || str_contains($m, 'taken')
            || str_contains($m, 'duplicate')
            || str_contains($m, 'already');
    }

    protected function resolveForgeDatabaseId(int $server_id, string $name): ?int
    {
        foreach ($this->forge->databases($server_id) as $db) {
            if (($db->name ?? null) === $name) {
                return (int) $db->id;
            }
        }

        return null;
    }

    protected function forgeDatabaseUserNameExistsOnServer(int $server_id, string $name): bool
    {
        foreach ($this->forge->databaseUsers($server_id) as $user) {
            if (($user->name ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sync DB_DATABASE, DB_USERNAME, and DB_PASSWORD in {@see EnvVariables} from the subscription (php + MySQL).
     * Creates or updates rows so they match {@see CustomerSubscription::forgeMysqlIdentifier} and {@see CustomerSubscription::forgeMysqlUser}.
     */
    public function syncMysqlEnvFromSubscription(CustomerSubscription $customerSubscription): void
    {
        if (! $customerSubscription->isPhpSubscriptionWithDatabase()) {
            return;
        }

        $customerSubscription->ensureDatabaseUserForForge();
        $customerSubscription->ensureDatabasePasswordForForge();
        $customerSubscription->refresh();

        if (! filled($customerSubscription->database_password)) {
            return;
        }

        $identifier = $customerSubscription->forgeMysqlIdentifier();
        $user = $customerSubscription->forgeMysqlUser();
        $values = [
            'DB_DATABASE' => $identifier,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => (string) $customerSubscription->database_password,
        ];

        foreach ($values as $key => $value) {
            EnvVariables::updateOrCreate(
                [
                    'key' => $key,
                    'customer_subscription_id' => $customerSubscription->id,
                ],
                ['value' => $value]
            );
        }
    }

    public function addMissingEnv(CustomerSubscription $customerSubscription)
    {
        if ($customerSubscription->customer) {
            $customerSubscription->loadMissing('subscriptionType');

            $addedEnv = EnvVariables::where('customer_subscription_id', $customerSubscription->id)->pluck('key');
            $missing = TemplateEnvVariables::where('subscription_type_id', $customerSubscription->subscription_type_id)
                ->whereNotIn('key', $addedEnv)
                ->get();

            foreach ($missing as $env) {
                EnvVariables::updateOrCreate([
                    'key' => $env->key,
                    'customer_subscription_id' => $customerSubscription->id,
                ], [
                    'value' => $env->initialEnvValue(),
                ]);
            }

            if (! $customerSubscription->isPhpSubscriptionWithDatabase()) {
                $database = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                    ->where('key', 'DB_DATABASE')
                    ->first();
                if ($database && filled($customerSubscription->database_name)) {
                    $database->value = $customerSubscription->database_name;
                    $database->save();
                }
            }

            $this->syncMysqlEnvFromSubscription($customerSubscription);

            $cmsUrl = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'VUE_APP_API_BASE_URL')
                ->first();

            if ($cmsUrl) {
                $caseManagement = CustomerSubscription::where('customer_id', $customerSubscription->customer_id)->where('subscription_type_id', 1)->first();
                if ($caseManagement) {
                    $cmsUrl->value = $caseManagement->url;
                    $cmsUrl->save();
                }
            }

            $cmsUrl = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'CMS_URL')
                ->first();

            if ($cmsUrl) {
                $caseManagement = CustomerSubscription::where('customer_id', $customerSubscription->customer_id)->where('subscription_type_id', 1)->first();
                if ($caseManagement) {
                    $cmsUrl->value = $caseManagement->url;
                    $cmsUrl->save();
                }
            }

            $appName = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'APP_NAME')
                ->first();
            if ($appName) {
                $appName->value = $customerSubscription->app_name;
                $appName->save();
            }

            $appName = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'VUE_APP_NAME')
                ->first();
            if ($appName) {
                $appName->value = $customerSubscription->app_name;
                $appName->save();
            }

            $appUrl = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'APP_URL')
                ->first();
            if ($appUrl) {
                $appUrl->value = $customerSubscription->url;
                $appUrl->save();
            }

            $elasticSearch = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'ELASTICSEARCH_INDEX')
                ->first();
            if ($elasticSearch) {
                $elasticSearch->value = $customerSubscription->database_name;
                $elasticSearch->save();
            }

            $secureToken = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'SECURE_TOKEN')
                ->first();
            if ($secureToken) {
                $secureToken->value = $customerSubscription->customer->token;
                $secureToken->save();
            }

            $minioBucket = EnvVariables::where('customer_subscription_id', $customerSubscription->id)
                ->where('key', 'MINIO_BUCKET')
                ->first();
            if ($minioBucket) {
                $minioBucket->value = $customerSubscription->database_name;
                $minioBucket->save();
            }
        }
    }

    public function sendEnv(CustomerSubscription $customerSubscription)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);

        if ($customerSubscription->hasIncompleteManualEnvVariables()) {
            Log::warning('forge.send_env.blocked_manual_vars', [
                'customer_subscription_id' => $customerSubscription->id,
            ]);
            throw new \RuntimeException(
                'Cannot push environment to Forge: one or more manual-required template variables are still empty. Fill them in Super Admin, or complete automated env for this subscription type, then retry.'
            );
        }

        $env = $this->collectEnv($customerSubscription);
        $this->forge->updateSiteEnvironmentFile($customerSubscription->server_id, $customerSubscription->forge_site_id, $env);
    }

    public function collectEnv($customerSubscription)
    {

        $envFileStr = '';
        $envVariables = EnvVariables::where('customer_subscription_id', $customerSubscription->id)->orderBy('key')->get();
        foreach ($envVariables as $env) {
            $value = $env->value ?? '';
            $envFileStr .= $env->key.'='.$value."\r";
        }

        return $envFileStr;

    }
}
