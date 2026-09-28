<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the shared LMS hub's local users.id separately from cms_user_id.
 *
 * Console and Firearm reply with their own user ids; those stay on cms_user_id.
 * The LMS hub is a different users table, so its id must not overwrite the Console link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_users', 'lms_user_id')) {
                $table->unsignedBigInteger('lms_user_id')->nullable()->after('cms_user_id');
            }
        });

        Schema::table('customer_users', function (Blueprint $table) {
            $table->unique(['customer_id', 'lms_user_id'], 'customer_users_customer_id_lms_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_users', function (Blueprint $table) {
            $table->dropUnique('customer_users_customer_id_lms_user_id_unique');
            $table->dropColumn('lms_user_id');
        });
    }
};
