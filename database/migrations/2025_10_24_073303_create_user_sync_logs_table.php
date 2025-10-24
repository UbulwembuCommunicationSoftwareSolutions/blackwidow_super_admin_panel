<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_user_id');
            $table->string('direction'); // 'inbound' or 'outbound'
            $table->string('status'); // 'success', 'failed', 'conflict'
            $table->text('error_message')->nullable();
            $table->json('sync_data')->nullable(); // Store the data that was synced
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->foreign('customer_user_id')->references('id')->on('customer_users')->onDelete('cascade');
            $table->index(['customer_user_id', 'direction']);
            $table->index(['status', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sync_logs');
    }
};
