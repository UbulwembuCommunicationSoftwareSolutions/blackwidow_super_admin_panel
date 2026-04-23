<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscription_deployment_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_subscription_id')->constrained()->cascadeOnDelete();
            $table->uuid('batch_id');
            $table->unsignedInteger('position');
            $table->string('job_name', 64);
            $table->json('parameters')->nullable();
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['customer_subscription_id', 'batch_id']);
            $table->index(['batch_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscription_deployment_jobs');
    }
};
