<?php

namespace Tests\Feature\Public;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Notifications\Admin\ServiceBookingActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Customer memilih mekanik (opsional) saat booking servis.
 */
class ServiceBookingMechanicChoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $mechanic;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $this->customer = User::factory()->create();
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'Joko Prasetyo']);
        $this->service = Service::factory()->create(['checklist' => "Ganti oli mesin\nCek aki"]);
        User::factory()->admin()->create();
    }

    private function book(array $overrides = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->customer)
            ->from(route('service-bookings.create'))
            ->post(route('service-bookings.store'), [
                'service_id' => $this->service->id, 'vehicle_model' => 'Nissan Livina VL', 'plate_number' => 'B 1234 ABC',
                'preferred_date' => '2026-10-12', 'preferred_time' => '09:00', 'phone' => '081234567890',
                'mechanic_id' => $this->mechanic->id, ...$overrides,
            ]);
    }

    public function test_form_menampilkan_mekanik_aktif_saja(): void
    {
        User::factory()->mechanic()->inactive()->create(['name' => 'Dedi Nonaktif']);

        $this->actingAs($this->customer)->get(route('service-bookings.create'))
            ->assertOk()
            ->assertSee('Mekanik (opsional)')
            ->assertSee('Bebas, dipilihkan bengkel')
            ->assertSee('Joko Prasetyo')
            ->assertDontSee('Dedi Nonaktif');
    }

    public function test_tanpa_mekanik_aktif_isian_tidak_tampil(): void
    {
        $this->mechanic->forceFill(['is_active' => false])->save();

        $this->actingAs($this->customer)->get(route('service-bookings.create'))->assertDontSee('Mekanik (opsional)');
    }

    public function test_booking_dengan_mekanik_pilihan(): void
    {
        $this->book()->assertSessionHasNoErrors()->assertRedirect(route('account.service-bookings.index'));

        $booking = ServiceBooking::query()->sole();
        $this->assertTrue($booking->isAssignedTo($this->mechanic));
        $this->assertTrue($booking->mechanic_chosen_by_customer);
        $this->assertSame(['Ganti oli mesin', 'Cek aki'], $booking->tasks()->pluck('name')->all());

        $admin = User::where('role', User::ROLE_ADMIN)->sole();
        $this->assertStringContainsString('mekanik pilihan: Joko Prasetyo', $admin->notifications()->sole()->data['message']);
        $this->assertSame(ServiceBookingActivity::class, $admin->notifications()->sole()->type);

        $this->get(route('account.service-bookings.index'))->assertSee('Mekanik pilihan Anda:')->assertSee('Joko Prasetyo');
        $this->actingAs($admin)->get(route('admin.service-bookings.show', $booking))->assertSee('Dipilih customer');
    }

    public function test_tanpa_mekanik_tetap_bisa_booking(): void
    {
        $this->book(['mechanic_id' => ''])->assertSessionHasNoErrors();

        $booking = ServiceBooking::query()->sole();
        $this->assertNull($booking->mechanic_id);
        $this->assertFalse($booking->mechanic_chosen_by_customer);
        $this->assertSame(0, $booking->tasks()->count());
    }

    public function test_mekanik_tidak_valid_ditolak(): void
    {
        $inactive = User::factory()->mechanic()->inactive()->create();
        $admin = User::factory()->admin()->create();

        foreach ([$inactive->id, $admin->id, $this->customer->id, 999999, 'abc'] as $mechanicId) {
            $this->book(['mechanic_id' => $mechanicId])->assertSessionHasErrors('mechanic_id');
        }

        $this->assertSame(0, ServiceBooking::count());
    }

    public function test_mekanik_yang_sedang_ada_pengerjaan_aktif_ditolak(): void
    {
        ServiceBooking::factory()->create([
            'status' => ServiceBooking::STATUS_CONFIRMED, 'mechanic_id' => $this->mechanic->id,
            'preferred_date' => '2026-10-12', 'preferred_time' => '09:00', 'plate_number' => 'D 1 X',
        ]);

        // Tanggal / jam sama ditolak
        $this->book()->assertSessionHasErrors([
            'mechanic_id' => 'Joko Prasetyo masih memiliki pengerjaan servis yang belum selesai. Silakan pilih mekanik lain atau biarkan dipilihkan bengkel.',
        ]);

        // Tanggal / jam lain juga tetap ditolak selama booking belum selesai
        $this->book(['preferred_date' => '2026-10-15', 'preferred_time' => '10:00'])
            ->assertSessionHasErrors([
                'mechanic_id' => 'Joko Prasetyo masih memiliki pengerjaan servis yang belum selesai. Silakan pilih mekanik lain atau biarkan dipilihkan bengkel.',
            ]);

        // Tanpa memilih mekanik (dipilihkan bengkel) tetap boleh.
        $this->book(['mechanic_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceBooking::count());
    }

    public function test_booking_selesai_atau_batal_tidak_dihitung_bentrok(): void
    {
        ServiceBooking::factory()->create([
            'status' => ServiceBooking::STATUS_COMPLETED, 'mechanic_id' => $this->mechanic->id,
            'preferred_date' => '2026-10-10', 'preferred_time' => '09:00', 'plate_number' => 'D 1 X',
        ]);

        ServiceBooking::factory()->create([
            'status' => ServiceBooking::STATUS_CANCELLED, 'mechanic_id' => $this->mechanic->id,
            'preferred_date' => '2026-10-11', 'preferred_time' => '09:00', 'plate_number' => 'D 2 Y',
        ]);

        $this->book()->assertSessionHasNoErrors();
    }

    public function test_form_menandai_mekanik_sibuk_sebagai_disabled(): void
    {
        ServiceBooking::factory()->create([
            'status' => ServiceBooking::STATUS_IN_PROGRESS, 'mechanic_id' => $this->mechanic->id,
            'preferred_date' => '2026-10-12', 'preferred_time' => '09:00', 'plate_number' => 'D 1 X',
        ]);

        $availableMechanic = User::factory()->mechanic()->create(['name' => 'Budi Santoso']);

        $response = $this->actingAs($this->customer)->get(route('service-bookings.create'));

        $response->assertOk()
            ->assertSee('Joko Prasetyo (Sedang ada pengerjaan)')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Budi Santoso (Sedang ada pengerjaan)')
            ->assertSee('<option value="'.$this->mechanic->id.'" disabled>Joko Prasetyo (Sedang ada pengerjaan)</option>', false);
    }

    public function test_admin_mengganti_mekanik_pilihan_customer(): void
    {
        $this->book();
        $booking = ServiceBooking::query()->sole();
        $other = User::factory()->mechanic()->create(['name' => 'Wayan Sudarma']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.service-bookings.assign-mechanic', $booking), ['mechanic_id' => $other->id])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertTrue($booking->isAssignedTo($other));
        $this->assertFalse($booking->mechanic_chosen_by_customer);
        $this->assertSame(2, $booking->tasks()->count(), 'Checklist tidak digandakan.');

        $this->actingAs($this->customer)->get(route('account.service-bookings.index'))
            ->assertDontSee('Mekanik pilihan Anda:')
            ->assertSee('Wayan Sudarma');
    }
}
