<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use App\Models\Reporting\Aims\CustomerDashboard;
use PDOException;
use Throwable;

/**
 * Reads and writes the AIMS key/value settings on one customer's reporting database.
 */
class AimsReportingSettingsStore
{
    public function __construct(
        private readonly TenantConnectionFactory $connections,
        private readonly AimsReportingSettings $settings,
        private readonly AimsRoleGrantInstaller $grants,
    ) {}

    /**
     * @return array{settings: list<array{key: string, value: string}>, roles: list<array{id: int, name: string}>}
     */
    public function read(CustomerDashboard $dashboard): array
    {
        return $this->withConnection($dashboard, fn (AimsReportConnection $connection): array => $this->snapshot($connection));
    }

    /**
     * @param  list<array{key?: mixed, value?: mixed}>  $pairs
     * @return array{settings: list<array{key: string, value: string}>, roles: list<array{id: int, name: string}>}
     */
    public function write(CustomerDashboard $dashboard, array $pairs): array
    {
        $document = $this->settings->document($pairs);

        return $this->withConnection($dashboard, function (AimsReportConnection $connection) use ($document): array {
            $pdo = $connection->pdo();
            $pdo->beginTransaction();
            try {
                $this->replaceGrants($connection, $document['grants']);
                if ($document['clock'] !== null) {
                    $clock = $connection->one(
                        'SELECT id FROM '.$connection->reporting('rpt_clock').' WHERE id = 1'
                    );
                    if ($clock === null) {
                        throw new TenantDatabaseException('The reporting clock row is missing. Re-run the reporting install.');
                    }
                    $updated = $connection->pdo()->prepare(
                        'UPDATE '.$connection->reporting('rpt_clock').' SET aims_offset_minutes = ? WHERE id = 1'
                    );
                    $updated->execute([$document['clock']]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            return $this->snapshot($connection);
        });
    }

    /**
     * @template T
     * @param  callable(AimsReportConnection): T  $callback
     * @return T
     */
    private function withConnection(CustomerDashboard $dashboard, callable $callback, bool $installed = false): mixed
    {
        try {
            return $callback($this->connections->connect($dashboard));
        } catch (PDOException $e) {
            if ($installed || ! $this->missingGrantTable($e)) {
                throw $this->schemaFailure($e);
            }
            $this->connections->disconnect();
            $this->grants->install($dashboard);

            return $this->withConnection($dashboard, $callback, true);
        } finally {
            $this->connections->disconnect();
        }
    }

    private function missingGrantTable(PDOException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($driverCode, [1146, 1054], true)
            || in_array((string) $exception->getCode(), ['42S02', '42S22'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $grants
     */
    private function replaceGrants(AimsReportConnection $connection, array $grants): void
    {
        $table = $connection->reporting('rpt_role_grant');
        $keys = array_column($grants, 'grant_key');

        if ($keys === []) {
            $connection->exec('DELETE FROM '.$table);
        } else {
            $statement = $connection->pdo()->prepare(
                'DELETE FROM '.$table.' WHERE grant_key NOT IN ('.implode(',', array_fill(0, count($keys), '?')).')'
            );
            $statement->execute($keys);
        }

        $upsert = $connection->pdo()->prepare(
            'INSERT INTO '.$table.' (grant_key, role_ids, scope, can_dashboard, can_cases, can_audit, can_export, can_see_names, note)
             VALUES (?, CAST(? AS JSON), ?, ?, ?, ?, ?, ?, ?) AS incoming
             ON DUPLICATE KEY UPDATE role_ids = incoming.role_ids, scope = incoming.scope,
               can_dashboard = incoming.can_dashboard, can_cases = incoming.can_cases, can_audit = incoming.can_audit,
               can_export = incoming.can_export, can_see_names = incoming.can_see_names, note = incoming.note'
        );

        foreach ($grants as $grant) {
            $upsert->execute([
                $grant['grant_key'],
                json_encode(array_values($grant['role_ids']), JSON_THROW_ON_ERROR),
                $grant['scope'],
                $grant['can_dashboard'],
                $grant['can_cases'],
                $grant['can_audit'],
                $grant['can_export'],
                $grant['can_see_names'],
                $grant['note'],
            ]);
        }
    }

    /**
     * @return array{settings: list<array{key: string, value: string}>, roles: list<array{id: int, name: string}>}
     */
    private function snapshot(AimsReportConnection $connection): array
    {
        $grants = $connection->rows(
            'SELECT grant_key, role_ids, scope, can_dashboard, can_cases, can_audit, can_export, can_see_names, note
             FROM '.$connection->reporting('rpt_role_grant').' ORDER BY grant_key'
        );
        $clock = $connection->one(
            'SELECT aims_offset_minutes FROM '.$connection->reporting('rpt_clock').' WHERE id = 1'
        );
        $roles = $connection->rows(
            'SELECT id, name FROM '.$connection->aims('roles').' ORDER BY name'
        );

        return [
            'settings' => $this->settings->pairs(
                $grants,
                $clock === null ? null : (int) $clock['aims_offset_minutes'],
            ),
            'roles' => array_map(static fn (array $role): array => [
                'id' => (int) $role['id'],
                'name' => (string) $role['name'],
            ], $roles),
        ];
    }

    private function schemaFailure(PDOException $exception): TenantDatabaseException
    {
        $code = (int) ($exception->errorInfo[1] ?? 0);
        if (in_array($code, [1146, 1054], true) || in_array((string) $exception->getCode(), ['42S02', '42S22'], true)) {
            return new TenantDatabaseException('Role grants could not be added to this reporting database.');
        }

        report($exception);

        return new TenantDatabaseException('The reporting settings could not be saved.');
    }
}
