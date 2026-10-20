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
        $host = $this->resolveHost($subscription, $dashboard->database_host);
        $port = $this->resolvePort($subscription);

        config([
            'database.connections.'.self::CONNECTION => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => (string) $port,
                'database' => $verifySchema ? $reportingDatabase : $aimsDatabase,
                'username' => $subscription->database_user,
                'password' => (string) $subscription->database_password,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options' => $this->pdoOptions(),
            ],
        ]);

        DB::purge(self::CONNECTION);

        try {
            $pdo = DB::connection(self::CONNECTION)->getPdo();
        } catch (Throwable $e) {
            $this->disconnect();

            throw $this->connectionFailure($e);
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

        return $options;
    }

    private function connectionFailure(Throwable $e): TenantDatabaseException
    {
        $code = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;

        if ($code === 1049) {
            return new TenantDatabaseException('The reporting schema is not installed on this customer database.');
        }

        report($e);

        return new TenantDatabaseException('The customer database could not be reached.');
    }
}
