<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('product');
            $table->string('name');
            $table->string('group_name');
            $table->string('sub_group_name')->default('General');
            $table->timestamps();

            $table->unique(['product', 'name']);
        });

        Schema::create('customer_user_product_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_user_id')->constrained('customer_users')->cascadeOnDelete();
            $table->foreignId('product_permission_id')->constrained('product_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['customer_user_id', 'product_permission_id'], 'customer_user_product_permission_unique');
        });

        $now = now();
        DB::table('product_permissions')->insert([
            [
                'product' => 'console',
                'name' => 'access super admin',
                'group_name' => 'Super Admin',
                'sub_group_name' => 'General',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'product' => 'firearm',
                'name' => 'access super admin',
                'group_name' => 'Super Admin',
                'sub_group_name' => 'General',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_user_product_permissions');
        Schema::dropIfExists('product_permissions');
    }
};
