<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Gabungkan role super_admin ke admin.
     */
    public function up(): void
    {
        // Pindahkan semua akun super_admin ke admin
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak perlu membalikkan role karena sudah melebur menjadi admin
    }
};
