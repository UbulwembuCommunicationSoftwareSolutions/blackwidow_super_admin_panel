<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use App\Models\Reporting\Aims\CustomerDashboard;
use Throwable;

/**
 * Creates rpt_role_grant on a reporting database installed before role ids.
 */
final class AimsRoleGrantInstaller
{
    public function __construct(
        private readonly TenantConnectionFactory $connections,
        private readonly AimsSchemaSql $schema,
    ) {}

    public function install(CustomerDashboard $dashboard): void
    {
        $dashboard->loadMissing('customerSubscription');
        $aimsDatabase = SqlName::assert((string) $dashboard->customerSubscription?->database_name);
        $reportingDatabase = SqlName::assert((string) $dashboard->reporting_database);
        $sql = $this->schema->render($this->schema->template('05_role_grants.sql'), $aimsDatabase, $reportingDatabase);

        try {
            $connection = $this->connections->connectForInstall($dashboard);
            foreach ($this->schema->statements($sql) as $statement) {
                $this->schema->execute($connection->pdo(), $statement);
            }
        } catch (TenantDatabaseException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new TenantDatabaseException('Role grants could not be added to this reporting database. '.$e->getMessage());
        } finally {
            $this->connections->disconnect();
        }
    }
}
