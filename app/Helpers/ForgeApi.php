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

    /**
     * Resolve the Forge organization slug that owns the given server, from the local
     * {@see ForgeServer} cache. Falls back to scanning every organization the API key
     * can see (used the first time a server is encountered, or after Forge migrates
     * a server between organizations) and caches the result.
     */
    public function organizationSlugForServer(int $server_id): string
    {
        $organization = ForgeServer::query()->where('forge_server_id', $server_id)->value('organization');
        if (filled($organization)) {
            return $organization;
        }

        $organization = $this->discoverOrganizationSlugForServer($server_id);
        if ($organization === null) {
            throw new \RuntimeException(
                'Could not find server ' . $server_id . ' in any Forge organization visible to this API token.'
            );
        }

        ForgeServer::query()->updateOrCreate(
            ['forge_server_id' => $server_id],
            ['organization' => $organization]
        );

        return $organization;
    }

    protected function discoverOrganizationSlugForServer(int $server_id): ?string
    {
        foreach ($this->forge->organizations()->lazy() as $organization) {
            foreach ($this->forge->servers($organization->slug)->lazy() as $server) {
                if ((int) $server->id === $server_id) {
                    return $organization->slug;
                }
            }
        }

        return null;
    }

    public function sendCommand($customerSubscriptionId, $command)
    {
        $customerSubscription = CustomerSubscription::find($customerSubscriptionId);
        if (! $customerSubscription) {
            throw new \InvalidArgumentException('Customer subscription not found: ' . $customerSubscriptionId);
        }
        $this->assertForgeSiteReady($customerSubscription);
        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        Log::info('forge.execute_site_command', [
            'customer_subscription_id' => (int) $customerSubscriptionId,
        ]);
        $this->forge->createCommand($organization, $customerSubscription->server_id, $customerSubscription->forge_site_id, [
            'command' => $command,
        ]);
    }

    public function horizonCreator($customerSubscription)
    {
        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        $data = [
            'command' => 'php /home/forge/' . $customerSubscription->domain . '/artisan horizon',
        ];
        $this->forge->createBackgroundProcess($organization, $customerSubscription->server_id, $data);
    }

    public function sendDeploymentScript(CustomerSubscription $customerSubscription)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        $this->forge->updateDeploymentScript($organization, $customerSubscription->server_id, $customerSubscription->forge_site_id, [
            'content' => $customerSubscription->deploymentScript()->first()->script,
        ]);
    }

    public function sendGitRepository($customerSubscription)
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        $this->forge->updateSite(
            $organization,
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
            echo 'Syncing Server: ' . $server->name . ' with ID of : ' . $server->forge_server_id . " \n";
            GetSitesForServerJob::dispatch($server->forge_server_id);
        }
    }

    /**
     * Fetch every server visible to this API token, across every organization it belongs to.
     *
     * @return list<\Laravel\Forge\Resources\Server>
     */
    public function getServers()
    {
        $servers = [];
        foreach ($this->forge->organizations()->lazy() as $organization) {
            foreach ($this->forge->servers($organization->slug)->lazy() as $server) {
                $servers[] = $server;
            }
        }
        $this->servers = $servers;

        return $this->servers;
    }

    public function getSites($serverId)
    {
        $sites = [];
        try {
            $organization = $this->organizationSlugForServer((int) $serverId);
            foreach ($this->forge->serverSites($organization, $serverId)->lazy() as $site) {
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

    public function deploySite($server_id, $site_id): \Laravel\Forge\Resources\Deployment
    {
        $organization = $this->organizationSlugForServer((int) $server_id);

        return $this->forge->createDeployment($organization, $server_id, $site_id);
    }

    /**
     * The site's current status on Forge (e.g. installing, installed, failed) — the real state of
     * the resource Forge is provisioning, as opposed to whether our last API call succeeded.
     */
    public function siteStatus(int $server_id, int $site_id): ?string
    {
        $organization = $this->organizationSlugForServer($server_id);

        return $this->forge->organizationSite($organization, $site_id)->status;
    }

    /**
     * Poll the site's status until it reaches one of $terminalStatuses or $timeoutSeconds elapses.
     * Returns the last observed status (which may not be terminal, if we timed out).
     */
    public function waitForSiteStatus(int $server_id, int $site_id, array $terminalStatuses, int $timeoutSeconds = 60): ?string
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $status = null;
        do {
            $status = $this->siteStatus($server_id, $site_id);
            if ($status !== null && in_array($status, $terminalStatuses, true)) {
                return $status;
            }
            usleep(1_500_000);
        } while (microtime(true) < $deadline);

        return $status;
    }

    /**
     * Poll a deployment until it reaches a terminal status (finished/failed/failed-build/cancelled)
     * or $timeoutSeconds elapses. Returns the last observed status.
     */
    public function waitForDeploymentStatus(int $server_id, int $site_id, int $deployment_id, int $timeoutSeconds = 240): string
    {
        $organization = $this->organizationSlugForServer($server_id);
        $terminal = ['finished', 'failed', 'failed-build', 'cancelled'];
        $deadline = microtime(true) + $timeoutSeconds;
        $status = 'pending';
        do {
            $deployment = $this->forge->deployment($organization, $server_id, $site_id, $deployment_id);
            $status = $deployment->status ?? $status;
            if (in_array($status, $terminal, true)) {
                return $status;
            }
            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        return $status;
    }

    public function deploymentLog(int $server_id, int $site_id, int $deployment_id): string
    {
        $organization = $this->organizationSlugForServer($server_id);

        return $this->forge->deploymentLog($organization, $server_id, $site_id, $deployment_id);
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
                        ->orWhere('url', 'like', '%://' . $site->name . '%');
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
                'Subscription ' . $fresh->id . ' is not ready for Forge API calls (missing server_id or forge_site_id).'
            );
        }

        return $fresh;
    }

    public function deployAllConsoles()
    {
        $customerSubscriptions = CustomerSubscription::where('subscription_type_id', 1)->get();
        foreach ($customerSubscriptions as $customerSubscription) {
            if ($customerSubscription->server_id == null || $customerSubscription->forge_site_id == null) {
                Log::error('Server ID or Site ID not found for Subscription ID: ' . $customerSubscription->id);
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

    /**
     * Request a Let's Encrypt certificate on Forge for the subscription's primary domain.
     * By default this does not block on Forge's polling ($waitUntilInstalled=false), since
     * DNS/HTTP challenge verification can take a while and we don't want to tie up a queue
     * worker or trigger job-timeout retries that re-request the certificate.
     *
     * @param  bool  $waitUntilInstalled  When true, blocks until the SDK reports the certificate is installed.
     */
    public function letsEncryptCertificate(CustomerSubscription $customerSubscription, bool $waitUntilInstalled = false): \Laravel\Forge\Resources\Certificate
    {
        $customerSubscription = $this->assertForgeSiteReady($customerSubscription);
        $domain = str_replace('http://', '', $customerSubscription->url);
        $domain = str_replace('https://', '', $domain);
        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        $serverId = (int) $customerSubscription->server_id;
        $siteId = (int) $customerSubscription->forge_site_id;

        $domainId = $this->resolveForgeDomainId($organization, $serverId, $siteId, $domain);
        if ($domainId === null) {
            $domainResource = $this->forge->createDomain($organization, $serverId, $siteId, [
                'name' => $domain,
            ]);
            $domainId = (int) $domainResource->id;
        }

        $certificate = $this->forge->createCertificate($organization, $serverId, $siteId, $domainId, [
            'type' => 'letsencrypt',
        ]);

        if ($waitUntilInstalled) {
            $certificate = $this->waitForCertificateInstalled($organization, $serverId, $siteId, $domainId, (int) $certificate->id);
        }

        Log::info('forge.letsencrypt_requested', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $serverId,
            'forge_site_id' => $siteId,
            'domain' => $domain,
            'wait_until_installed' => $waitUntilInstalled,
            'forge_status' => $certificate->status,
            'note' => $waitUntilInstalled
                ? null
                : 'Certificate may still be installing on Forge; check Forge if HTTPS is not live yet.',
        ]);

        return $certificate;
    }

    protected function waitForCertificateInstalled(string $organizationSlug, int $server_id, int $site_id, int $domain_id, int $certificate_id, int $timeoutSeconds = 30): \Laravel\Forge\Resources\Certificate
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $certificate = $this->forge->certificate($organizationSlug, $server_id, $site_id, $domain_id, $certificate_id);
            if ($certificate->status === 'installed') {
                return $certificate;
            }
            usleep(500000);
        } while (microtime(true) < $deadline);

        return $certificate;
    }

    protected function resolveForgeDomainId(string $organizationSlug, int $server_id, int $site_id, string $name): ?int
    {
        foreach ($this->forge->domains($organizationSlug, $server_id, $site_id)->lazy() as $domain) {
            if (($domain->name ?? null) === $name) {
                return (int) $domain->id;
            }
        }

        return null;
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
                'database' => $databaseName,
            ];
        } else {
            $payload = [
                'domain' => $customerSubscription->domain,
                'project_type' => $customerSubscription->subscriptionType->project_type,
                'directory' => $customerSubscription->subscriptionType->public_dir,
                'php_version' => 'php83',
                'nginx_template' => $customerSubscription->subscriptionType->nginx_template_id,
            ];
        }

        Log::info('forge.create_site', $payload);

        $organization = $this->organizationSlugForServer((int) $server_id);
        $site = $this->forge->createSite($organization, $server_id, $payload);
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
     *
     * @return string|null The database's Forge-side status (e.g. "installed"), or null if the step was skipped.
     */
    public function provisionForgeServerDatabase(int $server_id, CustomerSubscription $customerSubscription): ?string
    {
        $name = $customerSubscription->forgeMysqlIdentifier();
        if ($name === '') {
            Log::warning('forge.create_database.skip_missing_prereq', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database_empty' => true,
            ]);

            return null;
        }

        $organization = $this->organizationSlugForServer($server_id);
        $databasePayload = [
            'name' => $name,
        ];

        Log::info('forge.create_database', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $server_id,
            'database' => $name,
        ]);

        try {
            $createdDatabase = $this->forge->createDatabase($organization, $server_id, $databasePayload);
            $databaseId = (int) $createdDatabase->id;
            Log::info('forge.database_created', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
            ]);

            return $createdDatabase->status;
        } catch (ValidationException $e) {
            $validationMessage = $e->getMessage();
            $databaseId = $this->resolveForgeDatabaseId($organization, $server_id, $name);
            if ($databaseId !== null) {
                Log::info('forge.create_database.skip_exists', [
                    'customer_subscription_id' => $customerSubscription->id,
                    'server_id' => $server_id,
                    'database' => $name,
                    'database_id' => $databaseId,
                    'validation_message' => $validationMessage,
                    'forge_validation_errors' => $e->errors(),
                ]);

                return $this->forge->database($organization, $server_id, $databaseId)->status;
            }

            Log::warning('forge.create_database.validation_not_recovered', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'validation_message' => $validationMessage,
                'forge_validation_errors' => $e->errors(),
            ]);
            throw $e;
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
     *
     * @return string|null The database user's Forge-side status (e.g. "installed"), or null if the step was skipped.
     */
    public function provisionForgeServerDatabaseUser(int $server_id, CustomerSubscription $customerSubscription): ?string
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

            return null;
        }

        $organization = $this->organizationSlugForServer($server_id);
        $databaseId = $this->resolveForgeDatabaseId($organization, $server_id, $name);
        if ($databaseId === null) {
            $message = 'Forge MySQL database "' . $name . '" was not found on the server. Create the database step must succeed first.';
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
            $createdUser = $this->forge->createDatabaseUser($organization, $server_id, $userPayload);
            Log::info('forge.database_user_created', [
                'customer_subscription_id' => $customerSubscription->id,
                'server_id' => $server_id,
                'database' => $name,
                'database_id' => $databaseId,
                'mysql_user' => $user,
            ]);
            $customerSubscription->refresh();
            $this->syncMysqlEnvFromSubscription($customerSubscription);

            return $createdUser->status;
        } catch (ValidationException $e) {
            $validationMessage = $e->getMessage();
            $existingUser = $this->findForgeDatabaseUserByName($organization, $server_id, $user);
            if ($existingUser !== null || $this->isLikelyDuplicateDatabaseUserMessage($validationMessage)) {
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

                return $existingUser?->status;
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

    protected function resolveForgeDatabaseId(string $organizationSlug, int $server_id, string $name): ?int
    {
        foreach ($this->forge->databases($organizationSlug, $server_id)->lazy() as $db) {
            if (($db->name ?? null) === $name) {
                return (int) $db->id;
            }
        }

        return null;
    }

    protected function findForgeDatabaseUserByName(string $organizationSlug, int $server_id, string $name): ?\Laravel\Forge\Resources\DatabaseUser
    {
        foreach ($this->forge->databaseUsers($organizationSlug, $server_id)->lazy() as $user) {
            if (($user->name ?? null) === $name) {
                return $user;
            }
        }

        return null;
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

        $organization = $this->organizationSlugForServer((int) $customerSubscription->server_id);
        $env = $this->collectEnv($customerSubscription);
        Log::info('forge.update_site_environment_file', [
            'customer_subscription_id' => $customerSubscription->id,
            'server_id' => $customerSubscription->server_id,
            'forge_site_id' => $customerSubscription->forge_site_id,
        ]);
        Log::info(json_encode($env));
        try {
            $this->forge->updateSiteEnvironment($organization, $customerSubscription->server_id, $customerSubscription->forge_site_id, $env);
        } catch (ValidationException $e) {
            Log::error('forge.env.validation_failed', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        }
    }

    public function collectEnv($customerSubscription)
    {

        $envFileStr = '';
        $envVariables = EnvVariables::where('customer_subscription_id', $customerSubscription->id)->orderBy('key')->get();
        foreach ($envVariables as $env) {
            Log::info('forge.update_site_environment_file.env', [
                'key' => $env->key,
                'value' => $env->value,
            ]);
            $value = $env->value ?? '';
            $envFileStr .= $env->key . '=' . $value . "\r";
        }

        return $envFileStr;
    }
}
