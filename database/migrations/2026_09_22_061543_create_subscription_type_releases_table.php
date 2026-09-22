<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_type_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_type_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 64);
            $table->string('commit_sha', 40);
            $table->string('name')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('is_prerelease')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->boolean('requires_manual_rollback')->default(false);
            $table->unsignedBigInteger('github_release_id')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_type_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_type_releases');
    }
};
