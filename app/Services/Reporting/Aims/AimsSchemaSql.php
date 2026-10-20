<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

/**
 * Rewrites the eThekwini install scripts so the views read one customer's AIMS database.
 */
final class AimsSchemaSql
{
    public function render(string $sql, string $aimsDatabase, string $reportingDatabase): string
    {
        SqlName::assert($aimsDatabase);
        SqlName::assert($reportingDatabase);

        $sql = str_replace('ciid_console_aims_net_za', '___AIMS_DB_TOKEN___', $sql);
        $sql = str_replace('aims_rpt', $reportingDatabase, $sql);

        return str_replace('___AIMS_DB_TOKEN___', $aimsDatabase, $sql);
    }

    /**
     * @return list<string>
     */
    public function statements(string $sql): array
    {
        $parts = preg_split('/;\\r?\\n/', $sql) ?: [];
        $statements = [];

        foreach ($parts as $part) {
            $trimmed = trim((string) (preg_replace('#/\\*.*?\\*/#s', '', $part) ?? $part));
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
        }

        return $statements;
    }

    public function execute(\PDO $pdo, string $statement): void
    {
        if (preg_match('/^(SELECT|SHOW|WITH|EXPLAIN|DESCRIBE|DESC)\b/i', ltrim($statement)) !== 1) {
            $pdo->exec($statement);

            return;
        }

        $query = $pdo->query($statement);
        if ($query === false) {
            return;
        }

        $query->fetchAll();
        $query->closeCursor();
    }

    public function template(string $filename): string
    {
        $path = rtrim((string) config('reporting.schema_path'), '/').'/'.$filename;

        if (! is_file($path)) {
            throw new \RuntimeException('Reporting SQL template is missing: '.$filename);
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new \RuntimeException('Reporting SQL template could not be read: '.$filename);
        }

        return $sql;
    }
}
