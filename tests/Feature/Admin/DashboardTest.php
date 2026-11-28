<?php

namespace Tests\Feature\Admin;

use App\Models\Car;
use App\Models\MechanicRating;
use App\Models\PurchaseRequest;
use App\Models\ServiceBooking;
use App\Models\TestDrive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_customer_mendapat_403(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_melihat_angka_statistik_yang_benar(): void
    {
        $admin = User::factory()->admin()->create();
        $customers = User::factory()->count(4)->create();

        Car::factory()->count(3)->create(['stock' => 5]);
        Car::factory()->count(2)->outOfStock()->create();
        Car::factory()->inactive()->outOfStock()->create();
        $cars = Car::all();

        // Jadwal tetap (bukan besok): kartu "besok belum dikonfirmasi" tidak boleh ikut terhitung secara acak.
        $later = ['preferred_date' => today()->addDays(5)->toDateString()];
        TestDrive::factory()->count(2)->recycle($customers)->recycle($cars)->create($later);
        TestDrive::factory()->status('confirmed')->recycle($customers)->recycle($cars)->create($later);

        PurchaseRequest::factory()->count(3)->recycle($customers)->recycle($cars)->create();
        PurchaseRequest::factory()->status('approved')->recycle($customers)->recycle($cars)->create();

        ServiceBooking::factory()->recycle($customers)->create($later);
        ServiceBooking::factory()->status('confirmed')->recycle($customers)->create($later);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('stats', [
                'active_cars' => 5,
                'out_of_stock_cars' => 2,
                'pending_test_drives' => 2,
                'pending_purchases' => 3,
                'pending_service_bookings' => 1,
                'customers' => 4,
                // Semua jadwal di masa depan & pengajuan baru disetujui: belum ada yang perlu ditindaklanjuti.
                'overdue_payments' => 0,
                'overdue_test_drives' => 0,
                'overdue_service_bookings' => 0,
                'pending_refunds' => 0,
                'awaiting_response' => 0,
                'unconfirmed_test_drives_tomorrow' => 0,
                'unconfirmed_service_bookings_tomorrow' => 0,
                'active_spare_parts' => 0,
                'low_stock_spare_parts' => 0,
            ])
            ->assertDontSee('class="dashboard-attention', false)
            ->assertSeeInOrder(['Mobil Aktif', 'Stok Habis', 'Test Drive Pending', 'Pengajuan Pending', 'Servis Pending', 'Suku Cadang', 'Customer']);
    }

    public function test_tabel_hanya_menampilkan_lima_data_yang_relevan(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $car = Car::factory()->create();

        $purchases = collect(range(1, 7))->map(fn (int $daysAgo) => PurchaseRequest::factory()
            ->recycle([$customer, $car])
            ->create(['created_at' => now()->subDays($daysAgo)]));

        $yesterday = TestDrive::factory()->recycle([$customer, $car])
            ->create(['preferred_date' => today()->subDay()]);
        $cancelled = TestDrive::factory()->status('cancelled')->recycle([$customer, $car])
            ->create(['preferred_date' => today()]);
        $upcoming = collect(range(6, 1))->map(fn (int $days) => TestDrive::factory()
            ->status($days % 2 ? 'pending' : 'confirmed')
            ->recycle([$customer, $car])
            ->create(['preferred_date' => today()->addDays($days), 'preferred_time' => '10:00']));

        $response = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $response->assertViewHas('latestPurchases', fn ($items) => $items->pluck('id')->all()
            === $purchases->take(5)->pluck('id')->all());

        $response->assertViewHas('upcomingTestDrives', function ($items) use ($upcoming, $yesterday, $cancelled) {
            $expected = $upcoming->sortBy('preferred_date')->take(5)->pluck('id')->values()->all();

            return $items->pluck('id')->all() === $expected
                && ! $items->contains('id', $yesterday->id)
                && ! $items->contains('id', $cancelled->id);
        });
    }

    public function test_empty_state_tampil_saat_data_kosong(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Belum ada pengajuan')
            ->assertSee('Belum ada jadwal test drive')
            ->assertSee('Belum ada jadwal servis');
    }

    public function test_jumlah_query_tidak_bertambah_seiring_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->seedDashboardData(1);
        $queriesWithOneRow = $this->countDashboardQueries($admin);

        $this->seedDashboardData(4);
        $queriesWithFiveRows = $this->countDashboardQueries($admin);

        $this->assertSame($queriesWithOneRow, $queriesWithFiveRows);
    }

    public function test_sidebar_hanya_menampilkan_menu_yang_route_nya_ada(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('class="nav-link active" href="'.route('admin.dashboard').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('admin.cars.index').'"', false)
            ->assertSee('href="'.route('admin.promos.index').'"', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertSee('href="'.route('admin.test-drives.index').'"', false)
            ->assertSee('href="'.route('admin.purchase-requests.index').'"', false)
            ->assertSee('href="'.route('admin.spare-parts.index').'"', false)
            ->assertSee('href="'.route('admin.reports.index').'"', false)
            ->assertSee('href="'.route('admin.staff.index').'"', false)
            ->assertSee('href="'.route('admin.settings.edit').'"', false)
            ->assertSeeInOrder(['Dashboard', 'Notifikasi', 'Mobil', 'Merek', 'Kategori', 'Promo', 'Test Drive', 'Pengajuan', 'Booking Servis', 'Layanan Servis', 'Spare Part', 'Ulasan', 'Pengguna', 'Laporan', 'Staf', 'Pengaturan']);

        // Owner hanya melihat Dashboard, Laporan, Pengaturan (tidak ada Notifikasi dan Staf).
        $this->actingAs(User::factory()->owner()->create())->get('/admin/dashboard')
            ->assertSeeInOrder(['Dashboard', 'Laporan', 'Pengaturan'])
            ->assertDontSee('href="'.route('admin.staff.index').'"', false)
            ->assertDontSee('href="'.route('admin.spare-parts.index').'"', false)
            ->assertDontSee('href="'.route('admin.cars.index').'"', false);
    }

    public function test_kartu_pending_mengarah_ke_daftar_terfilter(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('href="'.e(route('admin.test-drives.index', ['status' => 'pending'])).'"', false)
            ->assertSee('href="'.e(route('admin.purchase-requests.index', ['status' => 'pending'])).'"', false)
            ->assertSee('href="'.e(route('admin.service-bookings.index', ['status' => 'pending'])).'"', false);
    }

    public function test_kartu_customer_mengarah_ke_daftar_pengguna_customer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('href="'.e(route('admin.users.index', ['role' => 'customer'])).'"', false);
    }

    public function test_dashboard_menampilkan_widget_performa_tim_terbaik(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $car = Car::factory()->create(['price' => 300_000_000]);

        $sales1 = User::factory()->sales()->create(['name' => 'Sales Bintang']);
        $sales2 = User::factory()->sales()->create(['name' => 'Sales Hebat']);

        // Sales 1 punya 2 unit terjual, Sales 2 punya 1 unit terjual
        PurchaseRequest::factory()->count(2)->status('approved')->recycle($car)->create([
            'sales_id' => $sales1->id, 'car_price' => 300_000_000,
        ]);
        PurchaseRequest::factory()->status('completed')->recycle($car)->create([
            'sales_id' => $sales2->id, 'car_price' => 200_000_000,
        ]);

        $mechanic1 = User::factory()->mechanic()->create(['name' => 'Mekanik Teladan']);
        $mechanic2 = User::factory()->mechanic()->create(['name' => 'Mekanik Handal']);

        $b1 = ServiceBooking::factory()->status('completed')->create(['mechanic_id' => $mechanic1->id]);
        $b2 = ServiceBooking::factory()->status('completed')->create(['mechanic_id' => $mechanic2->id]);

        MechanicRating::create(['service_booking_id' => $b1->id, 'mechanic_id' => $mechanic1->id, 'user_id' => $customer->id, 'rating' => 5]);
        MechanicRating::create(['service_booking_id' => $b2->id, 'mechanic_id' => $mechanic2->id, 'user_id' => $customer->id, 'rating' => 4]);

        $response = $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $response->assertSee('Performa Tim Terbaik')
            ->assertSee('Sales Terbaik')
            ->assertSee('Mekanik Terbaik')
            ->assertSee('Sales Bintang')
            ->assertSee('Sales Hebat')
            ->assertSee('Mekanik Teladan')
            ->assertSee('Mekanik Handal')
            ->assertSee('Terbaik #1');
    }

    public function test_dashboard_owner_menampilkan_widget_performa_tim_dengan_omset(): void
    {
        $owner = User::factory()->owner()->create();
        $sales = User::factory()->sales()->create(['name' => 'Sales Juara']);

        PurchaseRequest::factory()->status('approved')->create([
            'sales_id' => $sales->id, 'car_price' => 500_000_000,
        ]);

        $this->actingAs($owner)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Performa Tim Terbaik')
            ->assertSee('Sales Juara')
            ->assertSee('Rp 500.000.000');
    }

    public function test_leaderboard_tidak_menampilkan_sales_atau_mekanik_yang_belum_berkinerja(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->sales()->create(['name' => 'Sales Kosong']);
        User::factory()->mechanic()->create(['name' => 'Mekanik Kosong']);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Belum ada data sales')
            ->assertSee('Belum ada data mekanik')
            ->assertDontSee('Sales Kosong')
            ->assertDontSee('Mekanik Kosong')
            ->assertDontSee('Terbaik #1');
    }

    private function seedDashboardData(int $count): void
    {
        // Tiap baris memakai customer & mobil (dengan merek) berbeda agar N+1 pasti terlihat.
        TestDrive::factory()->count($count)->create();
        PurchaseRequest::factory()->count($count)->create();
        ServiceBooking::factory()->count($count)->create();
    }

    private function countDashboardQueries(User $admin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
