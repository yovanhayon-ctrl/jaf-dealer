<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Buat akun admin dari ADMIN_EMAIL / ADMIN_PASSWORD (.env).
     * Aman dijalankan ulang: jika email sudah ada, hanya memastikan role admin
     * (kata sandi tidak ditimpa).
     */
    public function run(): void
    {
        $email = config('dealer.admin.email');
        $password = config('dealer.admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException('ADMIN_EMAIL / ADMIN_PASSWORD belum diisi di .env.');
        }

        $user = User::firstWhere('email', $email);

        if ($user) {
            // Owner tetap owner; role lain dinaikkan menjadi admin.
            if (! $user->isAdmin()) {
                $user->role = User::ROLE_ADMIN;
            }
            if ($user->email_verified_at === null) {
                $user->email_verified_at = now();
            }
            if ($user->isDirty()) {
                $user->save();
            }

            $this->command?->info("Akun {$email} sudah ada (role {$user->roleLabel()}).");
            $this->seedStaff();

            return;
        }

        $user = new User([
            'name' => 'Administrator',
            'email' => $email,
            'password' => $password,
        ]);
        $user->role = User::ROLE_ADMIN;
        $user->email_verified_at = now();
        $user->save();

        $this->command?->info("Akun admin {$email} berhasil dibuat.");
        $this->seedStaff();
    }

    private function seedStaff(): void
    {
        if (app()->environment('local', 'testing')) {
            $sales2 = User::firstOrCreate(
                ['email' => 'sales2@gmail.com'],
                [
                    'name' => 'Sales 2',
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );
            if ($sales2->role !== User::ROLE_SALES) {
                $sales2->role = User::ROLE_SALES;
            }
            if ($sales2->email_verified_at === null) {
                $sales2->email_verified_at = now();
            }
            if ($sales2->isDirty()) {
                $sales2->save();
            }
        }
    }
}
