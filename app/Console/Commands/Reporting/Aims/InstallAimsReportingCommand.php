<?php

declare(strict_types=1);

namespace App\Console\Commands\Reporting\Aims;

use App\Models\Reporting\Aims\CustomerDashboard;
use App\Services\Reporting\Aims\AimsSchemaSql;
use App\Services\Reporting\Aims\SqlName;
use App\Services\Reporting\Aims\TenantConnectionFactory;
use App\Services\Reporting\Aims\TenantDatabaseException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InstallAimsReportingCommand extends Command
{
    protected $signature = 'reporting:install-aims
        {customer_dashboard : The customer dashboard id}
        {--sql-only : Write the SQL and do not connect}
        {--check : Run the post-install checks instead of installing}';

    protected $description = 'Install or check the AIMS reporting schema on a customer database';

    public function handle(TenantConnectionFactory $connections, AimsSchemaSql $schema): int
    {
        $dashboard = CustomerDashboard::query()
            ->with(['customerSubscription', 'dashboard'])
            ->find($this->argument('customer_dashboard'));

        if (! $dashboard) {
            $this->error('Customer dashboard not found.');

            return self::FAILURE;
        }

        $subscription = $dashboard->customerSubscription;
        if (! $subscription || blank($subscription->database_name) || blank($dashboard->reporting_database)) {
            $this->error('The subscription needs a database name and the dashboard needs a reporting database.');

            return self::FAILURE;
        }

        try {
            $aimsDatabase = SqlName::assert((string) $subscription->database_name);
            $reportingDatabase = SqlName::assert((string) $dashboard->reporting_database);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $template = $this->option('check')
            ? '04_post_install_checks_ethekwini.sql'
            : '02_reporting_schema_ethekwini.sql';

        try {
            $sql = $schema->render($schema->template($template), $aimsDatabase, $reportingDatabase);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('check')) {
            $sql = 'CREATE DATABASE IF NOT EXISTS `'.$reportingDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'."\n".$sql;
        }

        $relative = 'reporting/aims-'.$dashboard->id.($this->option('check') ? '-check' : '').'.sql';
        Storage::disk('local')->put($relative, $sql);
        $path = Storage::disk('local')->path($relative);
        $this->info('Wrote '.$path);

        if ($this->option('sql-only')) {
            return self::SUCCESS;
        }

        try {
            $connection = $connections->connect($dashboard, verifySchema: (bool) $this->option('check'));
        } catch (TenantDatabaseException $e) {
            $this->error($e->getMessage());
            $this->warn('The SQL file is ready for the server admin to run.');

            return self::FAILURE;
        }

        try {
            if ($this->option('check')) {
                $this->runChecks($connection->pdo(), $schema->statements($sql));

                return self::SUCCESS;
            }

            foreach ($schema->statements($sql) as $index => $statement) {
                $connection->pdo()->exec($statement);
                if (($index + 1) % 50 === 0) {
                    $this->line('Ran '.($index + 1).' statements...');
                }
            }

            $this->info('Reporting schema installed on '.$reportingDatabase.'.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Install stopped: '.$e->getMessage());
            $this->warn('The SQL file is ready for the server admin to run.');

            return self::FAILURE;
        } finally {
            $connections->disconnect();
        }
    }

    /**
     * @param  list<string>  $statements
     */
    private function runChecks(\PDO $pdo, array $statements): void
    {
        foreach ($statements as $statement) {
            if (! preg_match('/^(SELECT|SET)\b/i', ltrim($statement))) {
                continue;
            }

            if (stripos(ltrim($statement), 'SET ') === 0) {
                $pdo->exec($statement);

                continue;
            }

            $rows = $pdo->query($statement)?->fetchAll(\PDO::FETCH_ASSOC) ?? [];
            if ($rows !== []) {
                $this->table(array_keys($rows[0]), $rows);
            }
        }
    }
}
