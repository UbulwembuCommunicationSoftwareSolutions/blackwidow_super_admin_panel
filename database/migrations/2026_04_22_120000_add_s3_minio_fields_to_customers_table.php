<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('s3_endpoint', 512)->nullable();
            $table->string('s3_key', 255)->nullable();
            $table->string('s3_secret', 1024)->nullable();
            $table->string('s3_region', 32)->nullable();
            $table->string('s3_bucket', 255)->nullable();
            $table->boolean('s3_use_path_style_endpoint')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                's3_endpoint',
                's3_key',
                's3_secret',
                's3_region',
                's3_bucket',
                's3_use_path_style_endpoint',
            ]);
        });
    }
};
