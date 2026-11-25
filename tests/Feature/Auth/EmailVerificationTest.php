<?php

namespace Tests\Feature\Auth;

use App\Models\Brand;
use App\Models\Car;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedCustomer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'email_verified_at' => null,
        ]);
    }

    public function test_halaman_verifikasi_email_dapat_dibuka_oleh_customer_belum_terverifikasi(): void
    {
        $user = $this->unverifiedCustomer();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verifikasi Email Anda')
            ->assertSee($user->email);
    }

    public function test_customer_belum_terverifikasi_diblokir_dari_test_drive_beli_dan_booking_servis(): void
    {
        $user = $this->unverifiedCustomer();
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $car = Car::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 2,
        ]);
        $service = Service::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('test-drives.create'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('test-drives.store'), [])->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->get(route('purchase-requests.create', $car))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('purchase-requests.store', $car), [])->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->get(route('service-bookings.create'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('service-bookings.store', ['service_id' => $service->id]), [])->assertRedirect(route('verification.notice'));
    }

    public function test_customer_dapat_memverifikasi_email_via_signed_url_dan_mengakses_fitur(): void
    {
        $user = $this->unverifiedCustomer();
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $car = Car::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 2,
        ]);

        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->actingAs($user)
            ->get($verifyUrl)
            ->assertRedirect(route('home'))
            ->assertSessionHas('success');

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->actingAs($user->fresh())
            ->get(route('purchase-requests.create', $car))
            ->assertOk();
    }

    public function test_customer_dapat_kirim_ulang_tautan_verifikasi_dan_dibatasi_rate_limit(): void
    {
        Notification::fake();
        $user = $this->unverifiedCustomer();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)
                ->from(route('verification.notice'))
                ->post(route('verification.send'))
                ->assertRedirect(route('verification.notice'))
                ->assertSessionHas('success');
        }

        Notification::assertSentToTimes($user, VerifyEmail::class, 6);

        // Permintaan ke-7 dalam 1 menit dibatasi oleh throttle:6,1
        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertStatus(429);
    }

    public function test_kegagalan_smtp_saat_kirim_verifikasi_tidak_menyebabkan_error_500(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP connection refused'));

        $user = $this->unverifiedCustomer();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');
    }
}
