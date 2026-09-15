<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Establishes the two-way identity link with tenant apps.
 *
 * A customer user is identified to a tenant by this panel's own primary key, so
 * the old super_admin_user_id column here was self-referential and unused. Each
 * tenant app has its own users table and id sequence, so the remote id is stored
 * per app: cms_user_id now, firearm_user_id and friends as those apps come online.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_users', 'cms_user_id')) {
                $table->unsignedBigInteger('cms_user_id')->nullable()->after('customer_id');
            }
        });

        Schema::table('customer_users', function (Blueprint $table) {
            $table->unique(['customer_id', 'cms_user_id'], 'customer_users_customer_id_cms_user_id_unique');
        });

        $indexes = collect(Schema::getIndexes('customer_users'))->pluck('name');

        Schema::table('customer_users', function (Blueprint $table) use ($indexes) {
            foreach (['customer_users_super_admin_user_id_unique', 'customer_users_super_admin_user_id_index'] as $index) {
                if ($indexes->contains($index)) {
                    $table->dropIndex($index);
                }
            }
        });

        if (Schema::hasColumn('customer_users', 'super_admin_user_id')) {
            Schema::table('customer_users', function (Blueprint $table) {
                $table->dropColumn('super_admin_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->string('super_admin_user_id')->nullable()->after('id');
            $table->unique('super_admin_user_id');

            $table->dropUnique('customer_users_customer_id_cms_user_id_unique');
            $table->dropColumn('cms_user_id');
        });
    }
};
