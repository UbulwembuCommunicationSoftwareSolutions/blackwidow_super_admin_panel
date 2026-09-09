<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_subscription_deployment_jobs')) {
            return;
        }

        Schema::create('customer_subscription_deployment_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id');
            $table->uuid('batch_id');
            $table->unsignedInteger('position');
            $table->string('job_name', 64);
            $table->json('parameters')->nullable();
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Explicit short names: the default generated identifiers exceed MySQL's 64 character limit.
            $table->index(['customer_subscription_id', 'batch_id'], 'csdep_jobs_customer_subscription_id_batch_id_index');
            $table->index(['batch_id', 'position'], 'csdep_jobs_batch_id_position_index');
            $table->foreign('customer_subscription_id', 'csdep_jobs_customer_subscription_id_foreign')
                ->references('id')
                ->on('customer_subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscription_deployment_jobs');
    }
};
