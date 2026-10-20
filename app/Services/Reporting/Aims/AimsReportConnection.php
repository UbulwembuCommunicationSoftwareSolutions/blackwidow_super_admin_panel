<?php

declare(strict_types=1);

namespace App\Services\Reporting\Aims;

use PDO;
use PDOStatement;

/**
 * One request's connection to a customer's AIMS database and reporting schema.
 */
final class AimsReportConnection
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $aimsDatabase,
        private readonly string $reportingDatabase,
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function aimsDatabase(): string
    {
        return $this->aimsDatabase;
    }

    public function reportingDatabase(): string
    {
        return $this->reportingDatabase;
    }

    public function aims(string $table): string
    {
        return SqlName::qualify($this->aimsDatabase, $table);
    }

    public function reporting(string $object): string
    {
        return SqlName::qualify($this->reportingDatabase, $object);
    }

    /**
     * @param  list<mixed>  $params
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        $statement = $this->prepare($sql, $params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return $rows;
    }

    /**
     * @param  list<mixed>  $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $rows = $this->rows($sql, $params);

        return $rows[0] ?? null;
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * @param  list<mixed>  $params
     */
    private function prepare(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_values($params));

        return $statement;
    }
}
