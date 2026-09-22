<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_types', function (Blueprint $table) {
            $table->string('master_version', 64)->nullable()->change();
            $table->boolean('auto_promote_stable')->default(false)->after('master_version');
            $table->foreignId('current_release_id')
                ->nullable()
                ->after('auto_promote_stable')
                ->constrained('subscription_type_releases')
                ->nullOnDelete();
        });

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->string('deployed_version', 64)->nullable()->change();
            $table->foreignId('pinned_release_id')
                ->nullable()
                ->after('deployed_version')
                ->constrained('subscription_type_releases')
                ->nullOnDelete();
            $table->foreignId('deployed_release_id')
                ->nullable()
                ->after('pinned_release_id')
                ->constrained('subscription_type_releases')
                ->nullOnDelete();
            $table->string('deployed_commit_sha', 40)->nullable()->after('deployed_release_id');
            $table->string('deployed_tag_raw', 64)->nullable()->after('deployed_commit_sha');
            $table->timestamp('deployed_confirmed_at')->nullable()->after('deployed_tag_raw');
        });

        Schema::table('deployment_scripts', function (Blueprint $table) {
            $table->foreignId('rendered_release_id')
                ->nullable()
                ->after('script')
                ->constrained('subscription_type_releases')
                ->nullOnDelete();
            $table->boolean('is_custom')->default(false)->after('rendered_release_id');
        });
    }

    public function down(): void
    {
        Schema::table('deployment_scripts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rendered_release_id');
            $table->dropColumn('is_custom');
        });

        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pinned_release_id');
            $table->dropConstrainedForeignId('deployed_release_id');
            $table->dropColumn(['deployed_commit_sha', 'deployed_tag_raw', 'deployed_confirmed_at']);
            $table->string('deployed_version', 8)->nullable()->change();
        });

        Schema::table('subscription_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_release_id');
            $table->dropColumn('auto_promote_stable');
            $table->string('master_version', 8)->nullable()->change();
        });
    }
};
