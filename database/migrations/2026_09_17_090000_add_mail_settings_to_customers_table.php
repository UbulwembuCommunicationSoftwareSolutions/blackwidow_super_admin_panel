<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mail_mailer', 32)->nullable();
            $table->string('mail_transport', 32)->nullable();
            $table->string('mail_host', 255)->nullable();
            $table->string('mail_url', 512)->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_username', 255)->nullable();
            $table->string('mail_password', 1024)->nullable();
            $table->string('mail_encryption', 32)->nullable();
            $table->string('mail_scheme', 32)->nullable();
            $table->string('mail_from_address', 255)->nullable();
            $table->string('mail_from_name', 255)->nullable();
            $table->string('mail_ehlo_domain', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'mail_mailer',
                'mail_transport',
                'mail_host',
                'mail_url',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_scheme',
                'mail_from_address',
                'mail_from_name',
                'mail_ehlo_domain',
            ]);
        });
    }
};
