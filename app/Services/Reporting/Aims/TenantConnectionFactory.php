<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use App\Models\CustomerSubscription;
use App\Models\Reporting\Aims\CustomerDashboard;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

class TenantConnectionFactory
{
    public const CONNECTION = 'tenant_reporting';

    public function connect(CustomerDashboard $dashboard, bool $verifySchema = true): AimsReportConnection
    {
        $dashboard->loadMissing('customerSubscription.forgeServer');
        $subscription = $dashboard->customerSubscription;

        if (! $subscription || blank($subscription->database_name) || blank($subscription->database_user)) {
            throw new TenantDatabaseException('This subscription has no database credentials.');
        }

        if (blank($dashboard->reporting_database)) {
            throw new TenantDatabaseException('This dashboard has no reporting database.');
        }

        $aimsDatabase = SqlName::assert((string) $subscription->database_name);
        $reportingDatabase = SqlName::assert((string) $dashboard->reporting_database);
        $pdo = $this->open(array_merge($this->baseConnection($dashboard), [
            'database' => $verifySchema ? $reportingDatabase : $aimsDatabase,
            'username' => $subscription->database_user,
            'password' => (string) $subscription->database_password,
        ]), 'The customer database could not be reached.');

        if ($verifySchema) {
            try {
                $statement = $pdo->prepare('SELECT v FROM '.SqlName::qualify($reportingDatabase, 'rpt_settings')." WHERE k = 'aims_database'");
                $statement->execute();
                $built = $statement->fetchColumn();
            } catch (Throwable $e) {
                $this->disconnect();
                report($e);

                throw new TenantDatabaseException('The reporting schema is not installed on this customer database.');
            }

            if ($built === false) {
                $this->disconnect();

                throw new TenantDatabaseException('The reporting schema is not installed on this customer database.');
            }

            if ((string) $built !== $aimsDatabase) {
                $this->disconnect();

                throw new TenantDatabaseException('The reporting views were built for a different AIMS database.');
            }
        }

        return new AimsReportConnection($pdo, $aimsDatabase, $reportingDatabase);
    }

    public function connectForInstall(CustomerDashboard $dashboard): AimsReportConnection
    {
        $connection = $this->installationConnection($dashboard);
        $pdo = $this->open($connection, 'The install database user could not connect.');

        return new AimsReportConnection(
            $pdo,
            SqlName::assert((string) $connection['database']),
            SqlName::assert((string) $dashboard->reporting_database),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function installationConnection(CustomerDashboard $dashboard): array
    {
        $username = trim((string) config('reporting.install_username', ''));
        $password = (string) config('reporting.install_password', '');

        if ($username === '' || $password === '') {
            throw new TenantDatabaseException('The install database user is not configured.');
        }

        return array_merge($this->baseConnection($dashboard), [
            'database' => SqlName::assert((string) $dashboard->customerSubscription?->database_name),
            'username' => $username,
            'password' => $password,
        ]);
    }

    public function grantReportingAccess(PDO $pdo, string $siteUser, string $reportingDatabase): void
    {
        $siteUser = SqlName::assert($siteUser);
        $reportingDatabase = SqlName::assert($reportingDatabase);

        try {
            $statement = $pdo->prepare('SELECT Host FROM mysql.user WHERE User = ?');
            $statement->execute([$siteUser]);
            $hosts = array_values(array_filter(array_map(
                static fn (mixed $host): string => (string) $host,
                $statement->fetchAll(PDO::FETCH_COLUMN),
            )));
            $statement->closeCursor();
        } catch (Throwable $e) {
            report($e);

            throw new TenantDatabaseException('The install database user cannot read MySQL accounts.');
        }

        if ($hosts === []) {
            throw new TenantDatabaseException('The site database user does not exist on this MySQL server.');
        }

        try {
            foreach ($hosts as $host) {
                if (! preg_match('/^[A-Za-z0-9_%.:-]+$/', $host)) {
                    throw new TenantDatabaseException('The site database user has a host that cannot be granted.');
                }

                $pdo->exec(
                    'GRANT ALL PRIVILEGES ON `'.$reportingDatabase.'`.* TO \''.$siteUser.'\'@\''.$host.'\''
                );
            }
        } catch (TenantDatabaseException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new TenantDatabaseException('The site database user could not be granted access to the reporting database.');
        }
    }

    public function disconnect(): void
    {
        DB::disconnect(self::CONNECTION);
        DB::purge(self::CONNECTION);
    }

    public function resolveHost(CustomerSubscription $subscription, ?string $override = null): string
    {
        if (filled($override)) {
            return $override;
        }

        $subscription->loadMissing('forgeServer');
        $serverIp = $subscription->forgeServer?->ip_address;
        $apiIp = trim((string) config('reporting.api_server_ip', ''));

        if (filled($serverIp) && $apiIp !== '' && $apiIp !== $serverIp) {
            return (string) $serverIp;
        }

        return '127.0.0.1';
    }

    public function resolvePort(CustomerSubscription $subscription): int
    {
        $port = (int) $subscription->envVariables()->where('key', 'DB_PORT')->value('value');

        return $port > 0 ? $port : 3306;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConnection(CustomerDashboard $dashboard): array
    {
        $dashboard->loadMissing('customerSubscription.forgeServer');
        $subscription = $dashboard->customerSubscription;

        if (! $subscription || blank($subscription->database_name) || blank($subscription->database_user)) {
            throw new TenantDatabaseException('This subscription has no database credentials.');
        }

        if (blank($dashboard->reporting_database)) {
            throw new TenantDatabaseException('This dashboard has no reporting database.');
        }

        return [
            'driver' => 'mysql',
            'host' => $this->resolveHost($subscription, $dashboard->database_host),
            'port' => (string) $this->resolvePort($subscription),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => $this->pdoOptions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function open(array $connection, string $failureMessage): PDO
    {
        config([
            'database.connections.'.self::CONNECTION => $connection,
        ]);

        DB::purge(self::CONNECTION);

        try {
            $pdo = DB::connection(self::CONNECTION)->getPdo();
        } catch (Throwable $e) {
            $this->disconnect();

            throw $this->connectionFailure($e, $failureMessage);
        }

        try {
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $timeout = max(1000, (int) config('reporting.query_timeout_ms', 30000));
            $pdo->exec('SET SESSION max_execution_time = '.$timeout);
        } catch (Throwable $e) {
            $this->disconnect();
            report($e);

            throw new TenantDatabaseException('The customer database could not be prepared for reporting.');
        }

        return $pdo;
    }

    /**
     * @return array<int, mixed>
     */
    private function pdoOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
        }

        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
        }

        return $options;
    }

    private function connectionFailure(Throwable $e, string $failureMessage): TenantDatabaseException
    {
        $code = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;

        if ($code === 1049) {
            return new TenantDatabaseException('The reporting schema is not installed on this customer database.');
        }

        report($e);

        return new TenantDatabaseException($failureMessage);
    }
}
