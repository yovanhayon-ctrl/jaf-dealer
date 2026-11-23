<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingSparePart;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceBookingSparePartAndPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private User $mechanic;

    private Service $service;

    private ServiceBooking $booking;

    private SparePart $sparePart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-08 10:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->admin()->create();
        $this->customer = User::factory()->create(['name' => 'Budi Santoso', 'phone' => '08123456789']);
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'Joko Mekanik']);
        $this->service = Service::factory()->create(['name' => 'Tune Up & Servis Rem']);

        $this->booking = ServiceBooking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'mechanic_id' => $this->mechanic->id,
            'vehicle_model' => 'Nissan Grand Livina',
            'plate_number' => 'B 1234 ABC',
            'status' => ServiceBooking::STATUS_CONFIRMED,
            'preferred_date' => '2026-10-10',
            'preferred_time' => '09:00',
        ]);
        $this->booking->copyChecklistFromService();

        $this->sparePart = SparePart::factory()->create([
            'name' => 'Kampas Rem Depan Livina',
            'part_number' => 'BRK-001',
            'stock' => 10,
            'price' => 250000,
            'is_active' => true,
        ]);
    }

    public function test_admin_bisa_menambah_spare_part_rusak_ke_booking_servis(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.service-bookings.show', $this->booking))
            ->post(route('admin.service-bookings.spare-parts.store', $this->booking), [
                'spare_part_id' => $this->sparePart->id,
                'quantity' => 2,
                'condition_note' => 'Kampas rem aus parah dan berdecit',
            ]);

        $response->assertRedirect(route('admin.service-bookings.show', $this->booking).'#spare-parts');
        $response->assertSessionHas('success');

        // Pastikan record tersimpan dengan benar
        $this->assertDatabaseHas('service_booking_spare_parts', [
            'service_booking_id' => $this->booking->id,
            'spare_part_id' => $this->sparePart->id,
            'part_name' => 'Kampas Rem Depan Livina',
            'part_number' => 'BRK-001',
            'quantity' => 2,
            'price' => 250000,
            'subtotal' => 500000,
            'condition_note' => 'Kampas rem aus parah dan berdecit',
        ]);

        // Stok berkurang
        $this->assertSame(8, $this->sparePart->fresh()->stock);

        // Activity log tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service_booking.add_spare_part',
            'subject_id' => $this->booking->id,
        ]);
    }

    public function test_tambah_spare_part_melebihi_stok_akan_ditolak(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.service-bookings.show', $this->booking))
            ->post(route('admin.service-bookings.spare-parts.store', $this->booking), [
                'spare_part_id' => $this->sparePart->id,
                'quantity' => 15,
            ]);

        $response->assertSessionHasErrors('quantity', errorBag: 'sparePart');
        $this->assertSame(10, $this->sparePart->fresh()->stock);
        $this->assertDatabaseEmpty('service_booking_spare_parts');
    }

    public function test_admin_bisa_menghapus_spare_part_dan_mengembalikan_stok(): void
    {
        $item = ServiceBookingSparePart::factory()->create([
            'service_booking_id' => $this->booking->id,
            'spare_part_id' => $this->sparePart->id,
            'part_name' => $this->sparePart->name,
            'quantity' => 3,
        ]);
        $this->sparePart->decrement('stock', 3);
        $this->assertSame(7, $this->sparePart->fresh()->stock);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.service-bookings.show', $this->booking))
            ->delete(route('admin.service-bookings.spare-parts.destroy', [$this->booking, $item]));

        $response->assertRedirect(route('admin.service-bookings.show', $this->booking).'#spare-parts');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('service_booking_spare_parts', ['id' => $item->id]);
        $this->assertSame(10, $this->sparePart->fresh()->stock);

        // Activity log tercatat
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service_booking.remove_spare_part',
            'subject_id' => $this->booking->id,
        ]);
    }

    public function test_spare_part_tidak_bisa_ditambah_pada_booking_yang_sudah_selesai(): void
    {
        $this->booking->update(['status' => ServiceBooking::STATUS_COMPLETED]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.service-bookings.spare-parts.store', $this->booking), [
                'spare_part_id' => $this->sparePart->id,
                'quantity' => 1,
            ]);

        $response->assertSessionHas('error');
        $this->assertSame(10, $this->sparePart->fresh()->stock);
        $this->assertDatabaseEmpty('service_booking_spare_parts');
    }

    public function test_halaman_cetak_spk_dapat_diakses_dan_memuat_data_lengkap(): void
    {
        ServiceBookingSparePart::factory()->create([
            'service_booking_id' => $this->booking->id,
            'spare_part_id' => $this->sparePart->id,
            'part_name' => 'Kampas Rem Depan Livina',
            'part_number' => 'BRK-001',
            'quantity' => 1,
            'condition_note' => 'Pecah retak',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.service-bookings.print-work-order', $this->booking));

        $response->assertOk()
            ->assertViewIs('admin.service-bookings.print')
            ->assertSee('STRUK KERJA MEKANIK')
            ->assertSee('SPK-SRV-'.str_pad((string) $this->booking->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Nissan Grand Livina')
            ->assertSee('B 1234 ABC')
            ->assertSee('Budi Santoso')
            ->assertSee('Joko Mekanik')
            ->assertSee('CHECKLIST PEKERJAAN')
            ->assertSee('Kampas Rem Depan Livina')
            ->assertSee('Pecah retak')
            ->assertSee('Mekanik');
    }

    public function test_tombol_cetak_spk_tampil_pada_detail_booking_jika_mekanik_ditugaskan(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.service-bookings.show', $this->booking));

        $response->assertOk()
            ->assertSee(route('admin.service-bookings.print-work-order', $this->booking))
            ->assertSee('Cetak Nota Checklist');
    }

    public function test_stok_spare_part_otomatis_dipulihkan_saat_booking_servis_dibatalkan_oleh_admin(): void
    {
        ServiceBookingSparePart::factory()->create([
            'service_booking_id' => $this->booking->id,
            'spare_part_id' => $this->sparePart->id,
            'quantity' => 2,
        ]);
        $this->sparePart->decrement('stock', 2);
        $this->assertSame(8, $this->sparePart->fresh()->stock);

        $this->actingAs($this->admin)
            ->patch(route('admin.service-bookings.update-status', $this->booking), [
                'status' => ServiceBooking::STATUS_CANCELLED,
                'admin_note' => 'Customer berhalangan hadir',
            ])
            ->assertSessionHas('success');

        $this->assertSame(10, $this->sparePart->fresh()->stock);
    }

    public function test_stok_spare_part_otomatis_dipulihkan_saat_customer_membatalkan_booking(): void
    {
        $pendingBooking = ServiceBooking::factory()->create([
            'user_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'status' => ServiceBooking::STATUS_PENDING,
            'preferred_date' => '2026-10-15',
            'preferred_time' => '10:00',
        ]);

        ServiceBookingSparePart::factory()->create([
            'service_booking_id' => $pendingBooking->id,
            'spare_part_id' => $this->sparePart->id,
            'quantity' => 3,
        ]);
        $this->sparePart->decrement('stock', 3);
        $this->assertSame(7, $this->sparePart->fresh()->stock);

        $this->actingAs($this->customer)
            ->patch(route('account.service-bookings.cancel', $pendingBooking))
            ->assertSessionHas('success');

        $this->assertSame(10, $this->sparePart->fresh()->stock);
    }
}
