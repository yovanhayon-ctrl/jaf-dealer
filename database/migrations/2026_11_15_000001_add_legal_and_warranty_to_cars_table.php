<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('tax_status', 100)->nullable()->after('mileage');
            $table->string('document_status', 150)->nullable()->after('tax_status');
            $table->string('ownership_history', 100)->nullable()->after('document_status');
            $table->string('plate_type', 20)->nullable()->after('ownership_history');
            $table->string('warranty_info', 255)->nullable()->after('plate_type');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn([
                'tax_status',
                'document_status',
                'ownership_history',
                'plate_type',
                'warranty_info',
            ]);
        });
    }
};
