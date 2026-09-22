<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_brand_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->foreignId('customer_branding_media_id')
                ->nullable()
                ->constrained('customer_branding_media')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_brand_slots');
    }
};
