<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'customer_subscription_deployment_jobs';

    private const IDX_CSUB_BATCH = 'csdep_jobs_customer_subscription_id_batch_id_index';

    private const IDX_BATCH_POSITION = 'csdep_jobs_batch_id_position_index';

    private const FK_CSUB = 'csdep_jobs_customer_subscription_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->safeAddIndex(function (Blueprint $table) {
            $table->index(['customer_subscription_id', 'batch_id'], self::IDX_CSUB_BATCH);
        });

        $this->safeAddIndex(function (Blueprint $table) {
            $table->index(['batch_id', 'position'], self::IDX_BATCH_POSITION);
        });

        $this->safeAddForeignKey(function (Blueprint $table) {
            $table->foreign('customer_subscription_id', self::FK_CSUB)
                ->references('id')
                ->on('customer_subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            try {
                $table->dropForeign(self::FK_CSUB);
            } catch (QueryException) {
                // Constraint may not exist (e.g. table created without this migration on down).
            }
            try {
                $table->dropIndex(self::IDX_CSUB_BATCH);
            } catch (QueryException) {
            }
            try {
                $table->dropIndex(self::IDX_BATCH_POSITION);
            } catch (QueryException) {
            }
        });
    }

    private function safeAddIndex(\Closure $callback): void
    {
        try {
            Schema::table(self::TABLE, $callback);
        } catch (QueryException $e) {
            if ($this->isDuplicateSchemaObjectError($e)) {
                return;
            }
            throw $e;
        }
    }

    private function safeAddForeignKey(\Closure $callback): void
    {
        try {
            Schema::table(self::TABLE, $callback);
        } catch (QueryException $e) {
            if ($this->isDuplicateSchemaObjectError($e)) {
                return;
            }
            throw $e;
        }
    }

    private function isDuplicateSchemaObjectError(QueryException $e): bool
    {
        $message = $e->getMessage();
        $code = (string) $e->getCode();

        // MySQL: 1061 duplicate key name, 1826 duplicate FK, 1005 duplicate file, 1050 table (not used here)
        if (in_array($code, ['42S01', '42000', '23000', 'HY000'], true)) {
            if (str_contains($message, 'Duplicate key name')
                || str_contains($message, 'already exists')
                || str_contains($message, 'Duplicate foreign key')
                || str_contains($message, 'duplicate key')) {
                return true;
            }
        }

        return str_contains($message, 'already exists');
    }
};
