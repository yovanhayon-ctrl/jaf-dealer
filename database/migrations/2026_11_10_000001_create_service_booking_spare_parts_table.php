<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suku cadang / spare part untuk penggantian kerusakan kendaraan pada booking servis.
     */
    public function up(): void
    {
        Schema::create('service_booking_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spare_part_id')->nullable()->constrained('spare_parts')->nullOnDelete();
            $table->string('part_name', 150);
            $table->string('part_number', 50)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->string('condition_note', 255)->nullable();
            $table->timestamps();

            $table->index(['service_booking_id', 'spare_part_id'], 'sb_spare_parts_booking_part_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_booking_spare_parts');
    }
};
