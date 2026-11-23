<?php

namespace Tests\Feature\Admin;

use App\Models\Car;
use App\Models\PurchaseRequest;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\TestDrive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $superAdmin;

    private User $admin;

    private User $sales1;

    private User $sales2;

    private User $customerService;

    private User $mechanic;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create(['name' => 'Pak Owner']);
        $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'Pak Super Admin']);
        $this->admin = User::factory()->admin()->create(['name' => 'Pak Admin Dealer']);
        $this->sales1 = User::factory()->sales()->create(['name' => 'Sales Satu']);
        $this->sales2 = User::factory()->sales()->create(['name' => 'Sales Dua']);
        $this->customerService = User::factory()->customerService()->create(['name' => 'Mba CS']);
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'Mas Mekanik']);
        $this->customer = User::factory()->create(['name' => 'Budi Customer']);
    }

    public function test_tamu_dan_customer_ditolak_masuk_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_mekanik_tidak_bisa_masuk_panel_admin_dan_punya_panel_pekerjaan(): void
    {
        $this->actingAs($this->mechanic)->get(route('admin.dashboard'))
            ->assertForbidden();
        $this->get(route('mechanic.jobs.index'))
            ->assertOk();
    }

    public function test_owner_melihat_dashboard_pendapatan_dan_backup(): void
    {
        $car = Car::factory()->create(['price' => 300_000_000]);
        $service = Service::factory()->create(['price_from' => 500_000]);

        // Penjualan disetujui (dihitung pendapatan)
        PurchaseRequest::factory()->status('approved')->create([
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
            'car_price' => 300_000_000,
            'created_at' => now(),
        ]);

        // Servis selesai (dihitung pendapatan)
        ServiceBooking::factory()->status('completed')->create([
            'user_id' => $this->customer->id,
            'service_id' => $service->id,
            'service_price' => 500_000,
            'preferred_date' => today(),
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertSee('Pendapatan Bulan Ini')
            ->assertSee('Total Pendapatan Gabungan')
            ->assertSee('Pendapatan Penjualan')
            ->assertSee('Pendapatan Servis')
            ->assertSee('Rp 300.500.000') // Total gabungan
            ->assertSee('Rp 300.000.000') // Penjualan
            ->assertSee('Rp 500.000')     // Servis
            ->assertSee('Pengaturan Backup') // Tombol backup Owner
            ->assertDontSee('Tambah Mobil')  // Tidak ada tombol operasional harian
            ->assertDontSee('Tambah Promo');
    }

    public function test_super_admin_dan_admin_dealer_tidak_melihat_angka_rupiah(): void
    {
        $car = Car::factory()->create(['price' => 300_000_000]);
        PurchaseRequest::factory()->status('approved')->create([
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
            'car_price' => 300_000_000,
        ]);

        foreach ([$this->superAdmin, $this->admin] as $user) {
            $response = $this->actingAs($user)->get(route('admin.dashboard'));

            $response->assertOk()
                ->assertSee('Ringkasan Operasional')
                ->assertSee('Mobil Aktif')
                ->assertDontSee('Total Pendapatan')
                ->assertDontSee('Pendapatan Penjualan')
                ->assertDontSee('Pendapatan Servis')
                ->assertDontSee('Pengaturan Backup') // Tombol backup hanya owner
                ->assertDontSee('Rp 300.000.000');
        }
    }

    public function test_dashboard_sales_hanya_menampilkan_data_miliknya(): void
    {
        $car = Car::factory()->create();
        $customer1 = User::factory()->create(['name' => 'Customer Sales Satu']);
        $customer2 = User::factory()->create(['name' => 'Customer Sales Dua']);

        // Data milik Sales 1
        TestDrive::factory()->status('pending')->create([
            'sales_id' => $this->sales1->id,
            'user_id' => $customer1->id,
            'car_id' => $car->id,
            'preferred_date' => today(),
            'notes' => 'Catatan Sales 1',
        ]);
        PurchaseRequest::factory()->status('pending')->create([
            'sales_id' => $this->sales1->id,
            'user_id' => $customer1->id,
            'car_id' => $car->id,
            'notes' => 'Pengajuan Sales 1',
        ]);

        // Data milik Sales 2
        TestDrive::factory()->status('pending')->create([
            'sales_id' => $this->sales2->id,
            'user_id' => $customer2->id,
            'car_id' => $car->id,
            'preferred_date' => today(),
            'notes' => 'Catatan Sales 2 Rahasia',
        ]);
        PurchaseRequest::factory()->status('pending')->create([
            'sales_id' => $this->sales2->id,
            'user_id' => $customer2->id,
            'car_id' => $car->id,
            'notes' => 'Pengajuan Sales 2 Rahasia',
        ]);

        $response = $this->actingAs($this->sales1)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Tugas Saya')
            ->assertSee('Test Drive Saya (Pending)')
            ->assertSee('Pengajuan Saya (Pending)')
            ->assertSee('Customer Sales Satu')
            ->assertDontSee('Customer Sales Dua');

        // Di index juga hanya melihat miliknya
        $this->actingAs($this->sales1)->get(route('admin.test-drives.index'))
            ->assertOk()
            ->assertSee('Customer Sales Satu')
            ->assertDontSee('Customer Sales Dua');

        $this->actingAs($this->sales1)->get(route('admin.purchase-requests.index'))
            ->assertOk()
            ->assertSee('Customer Sales Satu')
            ->assertDontSee('Customer Sales Dua');
    }

    public function test_sales_tidak_dapat_melihat_detail_atau_ubah_data_sales_lain(): void
    {
        $car = Car::factory()->create();
        $td2 = TestDrive::factory()->status('pending')->create([
            'sales_id' => $this->sales2->id,
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
        ]);
        $pr2 = PurchaseRequest::factory()->status('pending')->create([
            'sales_id' => $this->sales2->id,
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
        ]);

        // Sales 1 mengakses data Sales 2 via URL -> 404
        $this->actingAs($this->sales1)->get(route('admin.test-drives.show', $td2))->assertNotFound();
        $this->actingAs($this->sales1)->patch(route('admin.test-drives.update-status', $td2), ['status' => 'confirmed'])->assertNotFound();

        $this->actingAs($this->sales1)->get(route('admin.purchase-requests.show', $pr2))->assertNotFound();
        $this->actingAs($this->sales1)->patch(route('admin.purchase-requests.update-status', $pr2), ['status' => 'processing'])->assertNotFound();
    }

    public function test_sales_tidak_boleh_menyetujui_pengajuan_pembelian(): void
    {
        $car = Car::factory()->create(['stock' => 5]);
        $pr = PurchaseRequest::factory()->status('processing')->create([
            'sales_id' => $this->sales1->id,
            'user_id' => $this->customer->id,
            'car_id' => $car->id,
        ]);

        // Sales mencoba ubah status ke approved (ditolak sistem)
        $this->actingAs($this->sales1)->patch(route('admin.purchase-requests.update-status', $pr), [
            'status' => 'approved',
            'admin_note' => 'Coba approve sendiri',
        ])->assertSessionHas('error');

        $this->assertSame('processing', $pr->fresh()->status);
        $this->assertSame(5, $car->fresh()->stock); // Stok tidak berkurang
    }

    public function test_dashboard_customer_service(): void
    {
        ServiceBooking::factory()->status('pending')->create(['preferred_date' => today()->addDay()]);
        TestDrive::factory()->status('pending')->create(['preferred_date' => today()->addDay()]);

        $response = $this->actingAs($this->customerService)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Pelayanan Pelanggan')
            ->assertSee('Booking Servis (Pending)')
            ->assertSee('Test Drive (Pending)')
            ->assertSee('Perlu Konfirmasi Segera (Jadwal Besok)');
    }

    public function test_akses_laporan_dan_isolasi_rupiah(): void
    {
        $car = Car::factory()->create(['price' => 200_000_000]);
        PurchaseRequest::factory()->status('approved')->create([
            'car_id' => $car->id,
            'car_price' => 200_000_000,
            'created_at' => now(),
        ]);

        // Sales & CS ditolak akses laporan (403)
        $this->actingAs($this->sales1)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->customerService)->get(route('admin.reports.index'))->assertForbidden();

        // Admin boleh akses laporan tetapi TANPA angka rupiah
        $adminReport = $this->actingAs($this->admin)->get(route('admin.reports.index'));
        $adminReport->assertOk()
            ->assertSee('Total Pengajuan')
            ->assertSee('Unit Terjual')
            ->assertDontSee('Nilai Penjualan')
            ->assertDontSee('Rp 200.000.000');

        // Owner boleh akses laporan LENGKAP dengan angka rupiah
        $ownerReport = $this->actingAs($this->owner)->get(route('admin.reports.index'));
        $ownerReport->assertOk()
            ->assertSee('Total Pengajuan')
            ->assertSee('Unit Terjual')
            ->assertSee('Nilai Penjualan')
            ->assertSee('Rp 200.000.000');
    }

    public function test_akses_pengaturan_owner_dan_admin(): void
    {
        $this->actingAs($this->owner)->get(route('admin.settings.edit'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk();

        // Sales dan CS ditolak (403)
        $this->actingAs($this->sales1)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($this->customerService)->get(route('admin.settings.edit'))->assertForbidden();
    }
}
