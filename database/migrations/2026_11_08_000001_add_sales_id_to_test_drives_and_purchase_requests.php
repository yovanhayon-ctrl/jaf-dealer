<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('test_drives', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('car_id')->constrained('users')->nullOnDelete();
            $table->index('sales_id');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('car_id')->constrained('users')->nullOnDelete();
            $table->index('sales_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_id');
        });

        Schema::table('test_drives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_id');
        });
    }
};
