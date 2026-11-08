<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Car;
use App\Models\PaymentProof;
use App\Models\Promo;
use App\Models\PurchaseRequest;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\TestDrive;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\Admin\AdminActivityNotification;
use App\Notifications\Admin\PaymentProofActivity;
use App\Notifications\Admin\PurchaseRequestActivity;
use App\Notifications\Admin\ServiceBookingActivity;
use App\Notifications\MechanicJobAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lima role staf (Owner, Admin, Sales, Customer Service, Mekanik) + Customer:
 * matriks izin, mode lihat saja, larangan aksi operasional untuk owner, larangan ubah akun owner bagi admin,
 * dan penerima notifikasi.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'owner' => User::factory()->owner(),
            'admin' => User::factory()->admin(),
            'sales' => User::factory()->sales(),
            'customer_service' => User::factory()->customerService(),
            'mechanic' => User::factory()->mechanic(),
            'customer' => User::factory(),
        ] as $role => $factory) {
            $this->users[$role] = $factory->create(['name' => 'User '.$role]);
        }
    }

    /**
     * Halaman GET => role yang boleh membukanya (selain itu 403).
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function pages(): array
    {
        $panel = ['owner', 'admin', 'sales', 'customer_service'];

        return [
            'dashboard' => ['admin.dashboard', $panel],
            'notifikasi' => ['admin.notifications.index', ['admin', 'sales', 'customer_service']],
            'mobil' => ['admin.cars.index', ['admin', 'sales', 'customer_service']],
            'tambah mobil' => ['admin.cars.create', ['admin']],
            'merek' => ['admin.brands.index', ['admin']],
            'kategori' => ['admin.categories.index', ['admin']],
            'promo' => ['admin.promos.index', ['admin', 'sales', 'customer_service']],
            'tambah promo' => ['admin.promos.create', ['admin']],
            'test drive' => ['admin.test-drives.index', ['admin', 'sales', 'customer_service']],
            'pengajuan' => ['admin.purchase-requests.index', ['admin', 'sales', 'customer_service']],
            'booking servis' => ['admin.service-bookings.index', ['admin', 'customer_service']],
            'layanan servis' => ['admin.services.index', ['admin', 'customer_service']],
            'ulasan' => ['admin.testimonials.index', ['admin', 'customer_service']],
            'pengguna' => ['admin.users.index', ['admin', 'customer_service']],
            'laporan' => ['admin.reports.index', ['owner', 'admin']],
            'staf' => ['admin.staff.index', ['admin']],
            'pengaturan' => ['admin.settings.edit', ['owner']],
            'pekerjaan mekanik' => ['mechanic.jobs.index', ['mechanic']],
            'layanan mekanik' => ['mechanic.services.index', ['mechanic']],
            'notifikasi mekanik' => ['mechanic.notifications.index', ['mechanic']],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('pages')]
    public function test_akses_halaman_per_role(string $route, array $allowed): void
    {
        foreach ($this->users as $role => $user) {
            $this->actingAs($user)->get(route($route))
                ->assertStatus(in_array($role, $allowed, true) ? 200 : 403, "{$role} membuka {$route}");
        }
    }

    public function test_owner_hanya_mengakses_dashboard_laporan_pengaturan(): void
    {
        $owner = $this->users['owner'];

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.settings.edit'))->assertOk();

        // Notifikasi, staf, dan operasional ditolak (403)
        $this->get(route('admin.notifications.index'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->get(route('admin.cars.index'))->assertForbidden();
        $this->get(route('admin.brands.index'))->assertForbidden();
        $this->get(route('admin.categories.index'))->assertForbidden();
        $this->get(route('admin.promos.index'))->assertForbidden();
        $this->get(route('admin.test-drives.index'))->assertForbidden();
        $this->get(route('admin.purchase-requests.index'))->assertForbidden();
        $this->get(route('admin.service-bookings.index'))->assertForbidden();
        $this->get(route('admin.services.index'))->assertForbidden();
        $this->get(route('admin.testimonials.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_sidebar_mengikuti_hak_akses(): void
    {
        // Owner hanya melihat Dashboard, Laporan, Pengaturan (tanpa Notifikasi dan Staf)
        $this->actingAs($this->users['owner'])->get(route('admin.dashboard'))
            ->assertSee('href="'.route('admin.dashboard').'"', false)
            ->assertSee('href="'.route('admin.reports.index').'"', false)
            ->assertSee('href="'.route('admin.settings.edit').'"', false)
            ->assertDontSee('href="'.route('admin.notifications.index').'"', false)
            ->assertDontSee('href="'.route('admin.staff.index').'"', false)
            ->assertDontSee('href="'.route('admin.cars.index').'"', false)
            ->assertDontSee('href="'.route('admin.brands.index').'"', false)
            ->assertDontSee('href="'.route('admin.categories.index').'"', false)
            ->assertDontSee('href="'.route('admin.promos.index').'"', false)
            ->assertDontSee('href="'.route('admin.test-drives.index').'"', false)
            ->assertDontSee('href="'.route('admin.purchase-requests.index').'"', false)
            ->assertDontSee('href="'.route('admin.service-bookings.index').'"', false)
            ->assertDontSee('href="'.route('admin.services.index').'"', false)
            ->assertDontSee('href="'.route('admin.testimonials.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false);

        // Admin melihat seluruh menu operasional + Laporan & Staf, tetapi TIDAK melihat Pengaturan
        $this->actingAs($this->users['admin'])->get(route('admin.dashboard'))
            ->assertSee('href="'.route('admin.cars.index').'"', false)
            ->assertSee('href="'.route('admin.brands.index').'"', false)
            ->assertSee('href="'.route('admin.categories.index').'"', false)
            ->assertSee('href="'.route('admin.promos.index').'"', false)
            ->assertSee('href="'.route('admin.test-drives.index').'"', false)
            ->assertSee('href="'.route('admin.purchase-requests.index').'"', false)
            ->assertSee('href="'.route('admin.service-bookings.index').'"', false)
            ->assertSee('href="'.route('admin.services.index').'"', false)
            ->assertSee('href="'.route('admin.testimonials.index').'"', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertSee('href="'.route('admin.reports.index').'"', false)
            ->assertSee('href="'.route('admin.staff.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.edit').'"', false);

        $this->actingAs($this->users['sales'])->get(route('admin.dashboard'))
            ->assertSee('href="'.route('admin.cars.index').'"', false)
            ->assertSee('href="'.route('admin.promos.index').'"', false)
            ->assertSee('href="'.route('admin.test-drives.index').'"', false)
            ->assertSee('href="'.route('admin.purchase-requests.index').'"', false)
            ->assertDontSee('href="'.route('admin.service-bookings.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('admin.reports.index').'"', false)
            ->assertDontSee('href="'.route('admin.staff.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.edit').'"', false);

        $this->actingAs($this->users['customer_service'])->get(route('admin.dashboard'))
            ->assertSee('href="'.route('admin.service-bookings.index').'"', false)
            ->assertSee('href="'.route('admin.testimonials.index').'"', false)
            ->assertSee('href="'.route('admin.cars.index').'"', false)
            ->assertSee('href="'.route('admin.promos.index').'"', false)
            ->assertSee('href="'.route('admin.test-drives.index').'"', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('admin.staff.index').'"', false)
            ->assertDontSee('href="'.route('admin.reports.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.edit').'"', false);

        $this->actingAs($this->users['mechanic'])->get(route('mechanic.jobs.index'))
            ->assertSee('href="'.route('mechanic.services.index').'"', false)
            ->assertSee('href="'.route('mechanic.notifications.index').'"', false);
    }

    public function test_sales_hanya_melihat_mobil_dan_promo(): void
    {
        $car = Car::factory()->create(['name' => 'Magnite Premium']);
        $promo = Promo::factory()->create();
        $sales = $this->users['sales'];

        $this->actingAs($sales)->get(route('admin.cars.index'))
            ->assertOk()
            ->assertSee('Magnite Premium')
            ->assertDontSee(route('admin.cars.create'), false)
            ->assertDontSee(route('admin.cars.edit', $car), false)
            ->assertDontSee(route('admin.cars.toggle-active', $car), false)
            ->assertDontSee('data-delete-url', false);
        $this->get(route('admin.promos.index'))->assertOk()->assertDontSee(route('admin.promos.edit', $promo), false);

        $this->get(route('admin.cars.edit', $car))->assertForbidden();
        $this->put(route('admin.cars.update', $car), ['name' => 'X'])->assertForbidden();
        $this->patch(route('admin.cars.toggle-active', $car))->assertForbidden();
        $this->get(route('admin.cars.images.index', $car))->assertForbidden();
        $this->delete(route('admin.promos.destroy', $promo))->assertForbidden();
        $this->assertModelExists($promo);
    }

    public function test_hapus_data_master_oleh_admin(): void
    {
        foreach (['owner', 'sales', 'customer_service'] as $role) {
            $brand = Brand::factory()->create();
            $this->actingAs($this->users[$role])->delete(route('admin.brands.destroy', $brand))->assertForbidden();
            $this->assertModelExists($brand);
        }

        $brand = Brand::factory()->create();
        $this->actingAs($this->users['admin'])->get(route('admin.brands.index'))->assertSee('data-delete-url', false);
        $this->delete(route('admin.brands.destroy', $brand))->assertRedirect(route('admin.brands.index'));
        $this->assertModelMissing($brand);
    }

    public function test_pengajuan_sales_boleh_ubah_status_tanpa_urusan_pembayaran(): void
    {
        $sales = $this->users['sales'];
        $purchase = PurchaseRequest::factory()->status('processing')->create(['sales_id' => $sales->id]);
        $proof = PaymentProof::factory()->recycle($purchase)->create(['purchase_request_id' => $purchase->id]);

        $this->actingAs($sales)->get(route('admin.purchase-requests.show', $purchase))
            ->assertOk()
            ->assertSee(route('admin.purchase-requests.update-status', $purchase), false)
            ->assertDontSee(route('admin.payment-proofs.approve', [$purchase, $proof]), false)
            ->assertDontSee(route('admin.payment-proofs.file', [$purchase, $proof]), false)
            ->assertDontSee('Catat Pembayaran di Showroom');
        $this->patch(route('admin.payment-proofs.approve', [$purchase, $proof]))->assertForbidden();
        $this->get(route('admin.payment-proofs.file', [$purchase, $proof]))->assertForbidden();
        $this->post(route('admin.payment-refunds.store', $purchase), [])->assertForbidden();
        $this->assertTrue($proof->fresh()->isPending());

        $this->patch(route('admin.purchase-requests.update-status', $purchase), ['status' => 'rejected', 'admin_note' => 'Ditolak karena alasan tertentu'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $purchase->fresh()->status);
    }

    public function test_customer_service_hanya_melihat_pengajuan_dan_ulasan(): void
    {
        $purchase = PurchaseRequest::factory()->status('pending')->create(['admin_note' => 'Menunggu dokumen.']);
        $testimonial = Testimonial::factory()->create();
        $cs = $this->users['customer_service'];

        $this->actingAs($cs)->get(route('admin.purchase-requests.show', $purchase))
            ->assertOk()
            ->assertDontSee(route('admin.purchase-requests.update-status', $purchase), false)
            ->assertSee('Menunggu dokumen.');
        $this->patch(route('admin.purchase-requests.update-status', $purchase), ['status' => 'processing'])->assertForbidden();
        $this->assertSame('pending', $purchase->fresh()->status);

        $this->get(route('admin.testimonials.index'))->assertOk()->assertDontSee(route('admin.testimonials.approve', $testimonial), false);
        $this->patch(route('admin.testimonials.approve', $testimonial))->assertForbidden();
    }

    public function test_admin_mengelola_operasional_tetapi_tanpa_pengaturan(): void
    {
        $admin = $this->users['admin'];
        $purchase = PurchaseRequest::factory()->status('approved')->create();
        $proof = PaymentProof::factory()->recycle($purchase)->create(['purchase_request_id' => $purchase->id]);
        $promo = Promo::factory()->create();

        // Pengajuan: Admin boleh kelola status dan bukti pembayaran
        $this->actingAs($admin)->get(route('admin.purchase-requests.show', $purchase))
            ->assertOk()
            ->assertSee(route('admin.purchase-requests.update-status', $purchase), false)
            ->assertSee(route('admin.payment-proofs.approve', [$purchase, $proof]), false);
        $this->patch(route('admin.payment-proofs.approve', [$purchase, $proof]))->assertSessionHasNoErrors();
        $this->assertSame(PaymentProof::STATUS_APPROVED, $proof->fresh()->status);

        // Promo: Admin boleh mengelola promo (L T U H)
        $this->get(route('admin.promos.index'))->assertOk()
            ->assertSee(route('admin.promos.edit', $promo), false);
        $this->get(route('admin.promos.edit', $promo))->assertOk();
        $this->delete(route('admin.promos.destroy', $promo))->assertRedirect(route('admin.promos.index'));
        $this->assertModelMissing($promo);

        // Laporan: Admin boleh melihat laporan operasional (tanpa rupiah)
        $this->get(route('admin.reports.index'))->assertOk();

        // Pengaturan: Admin TIDAK boleh mengakses (403 khusus Owner)
        $this->get(route('admin.settings.edit'))->assertForbidden();

        // Staf: Admin boleh kelola staf
        $this->get(route('admin.staff.index'))->assertOk();
    }

    public function test_customer_service_mengurus_test_drive_tetapi_hanya_melihat_mobil_dan_promo(): void
    {
        $testDrive = TestDrive::factory()->create(['status' => 'pending', 'admin_note' => 'Hubungi ulang sore.']);
        $car = Car::factory()->create();
        $promo = Promo::factory()->create();

        // Customer Service boleh ubah status & jadwal test drive (L S sesuai matriks).
        $this->actingAs($this->users['customer_service'])->get(route('admin.test-drives.show', $testDrive))
            ->assertOk()
            ->assertSee('Hubungi ulang sore.')
            ->assertSee(route('admin.test-drives.update-status', $testDrive), false)
            ->assertSee(route('admin.test-drives.reschedule', $testDrive), false);
        $this->patch(route('admin.test-drives.update-status', $testDrive), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $testDrive->fresh()->status);
        $this->patch(route('admin.test-drives.reschedule', $testDrive), [
            'preferred_date' => today()->addDays(3)->toDateString(), 'preferred_time' => TestDrive::TIME_SLOTS[0],
        ])->assertSessionHasNoErrors();

        // CS hanya melihat mobil dan promo (L).
        $this->get(route('admin.cars.index'))->assertOk()->assertDontSee(route('admin.cars.edit', $car), false);
        $this->get(route('admin.cars.edit', $car))->assertForbidden();
        $this->get(route('admin.promos.index'))->assertOk()->assertDontSee(route('admin.promos.edit', $promo), false);
        $this->get(route('admin.promos.create'))->assertForbidden();

        // Sales tetap mengurus test drive miliknya.
        $testDrive->update(['sales_id' => $this->users['sales']->id]);
        $this->actingAs($this->users['sales'])->get(route('admin.test-drives.show', $testDrive))
            ->assertSee(route('admin.test-drives.update-status', $testDrive), false);
        $this->patch(route('admin.test-drives.update-status', $testDrive), ['status' => 'cancelled', 'admin_note' => 'Customer berhalangan hadir'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $testDrive->fresh()->status);
    }

    public function test_customer_service_mengelola_booking_servis(): void
    {
        $booking = ServiceBooking::factory()->create();
        $mechanic = $this->users['mechanic'];

        $this->actingAs($this->users['customer_service'])
            ->patch(route('admin.service-bookings.update-status', $booking), ['status' => 'confirmed'])
            ->assertSessionHasNoErrors();
        $this->patch(route('admin.service-bookings.assign-mechanic', $booking), ['mechanic_id' => $mechanic->id])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($mechanic->id, $booking->mechanic_id);
    }

    public function test_customer_service_hanya_melihat_customer_di_pengguna(): void
    {
        $cs = $this->users['customer_service'];

        $this->actingAs($cs)->get(route('admin.users.index', ['role' => 'semua']))
            ->assertOk()
            ->assertSee('User customer')
            ->assertDontSee('User owner')
            ->assertDontSee('id="filter_role"', false);
        $this->get(route('admin.users.show', $this->users['customer']))->assertOk();
        $this->get(route('admin.users.show', $this->users['admin']))->assertNotFound();

        $this->actingAs($this->users['admin'])->get(route('admin.users.show', $this->users['sales']))->assertOk();
    }

    public function test_admin_mengelola_staf_tetapi_tidak_bisa_mengubah_atau_menghapus_owner(): void
    {
        $admin = $this->users['admin'];
        $owner = $this->users['owner'];

        $this->actingAs($admin)->get(route('admin.staff.create'))
            ->assertOk()
            ->assertSee('value="sales"', false)
            ->assertSee('value="customer_service"', false)
            ->assertSee('value="mechanic"', false)
            ->assertSee('value="admin"', false)
            ->assertDontSee('value="owner"', false);

        // Boleh ubah staf biasa
        $this->get(route('admin.staff.edit', $this->users['sales']))->assertOk();

        // TIDAK BISA edit akun Owner (404)
        $this->get(route('admin.staff.edit', $owner))->assertNotFound();
        $this->get(route('admin.staff.edit', $admin))->assertNotFound();

        // TIDAK BISA update email atau password Owner (404)
        $this->put(route('admin.staff.update', $owner), [
            'name' => 'Owner Palsu',
            'email' => 'ownerpalsu@dealer.test',
            'role' => 'admin',
            'password' => 'passbaru123',
            'password_confirmation' => 'passbaru123',
        ])->assertNotFound();

        // Password & email Owner tidak berubah
        $owner->refresh();
        $this->assertNotSame('ownerpalsu@dealer.test', $owner->email);

        // TIDAK BISA membuat akun dengan role owner
        $this->post(route('admin.staff.store'), [
            'name' => 'Calon Owner', 'email' => 'calonowner@dealer.test', 'role' => 'owner',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'calonowner@dealer.test']);

        // Boleh membuat staf biasa (misal sales)
        $this->post(route('admin.staff.store'), [
            'name' => 'Sinta Sales', 'email' => 'sinta@dealer.test', 'role' => 'sales',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('admin.staff.index'));
        $this->assertDatabaseHas('users', ['email' => 'sinta@dealer.test', 'role' => 'sales']);

        // Admin bisa menghapus staf lain (misal sales)
        $this->actingAs($admin)->delete(route('admin.staff.destroy', $this->users['sales']))
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success');
        $this->assertModelMissing($this->users['sales']);

        // Admin TIDAK BISA menghapus Owner (404)
        $this->actingAs($admin)->delete(route('admin.staff.destroy', $owner))->assertNotFound();
        $this->assertModelExists($owner);

        // Admin TIDAK BISA menghapus akun sendiri (404)
        $this->actingAs($admin)->delete(route('admin.staff.destroy', $admin))->assertNotFound();
        $this->assertModelExists($admin);

        // Owner tidak bisa membuka menu staf (403)
        $this->actingAs($owner)->get(route('admin.staff.index'))->assertForbidden();
    }

    public function test_notifikasi_admin_hanya_ke_role_yang_berhak(): void
    {
        AdminActivityNotification::notifyAdmins(new PurchaseRequestActivity(PurchaseRequest::factory()->create()));
        AdminActivityNotification::notifyAdmins(new ServiceBookingActivity(ServiceBooking::factory()->create()));
        $purchase = PurchaseRequest::factory()->status('approved')->create();
        AdminActivityNotification::notifyAdmins(new PaymentProofActivity(
            PaymentProof::factory()->recycle($purchase)->create(['purchase_request_id' => $purchase->id]), $purchase,
        ));

        $received = fn (string $role) => $this->users[$role]->notifications()->pluck('type')
            ->map(fn ($type) => class_basename($type))->sort()->values()->all();

        $this->assertSame(['PaymentProofActivity', 'PurchaseRequestActivity', 'ServiceBookingActivity'], $received('admin'));
        $this->assertSame(['PurchaseRequestActivity'], $received('sales'));
        $this->assertSame(['PurchaseRequestActivity', 'ServiceBookingActivity'], $received('customer_service'));
        $this->assertSame([], $received('mechanic'));
    }

    public function test_mekanik_diberi_tahu_pekerjaan_baru_dan_melihat_layanan(): void
    {
        $mechanic = $this->users['mechanic'];
        Service::factory()->create(['name' => 'Ban & Aki', 'checklist' => "Cek tekanan ban\nCek aki", 'is_active' => true]);
        $booking = ServiceBooking::factory()->create(['mechanic_id' => $mechanic->id]);

        $this->assertSame(0, $mechanic->notifications()->count());
        $this->actingAs($this->users['admin'])->patch(route('admin.service-bookings.update-status', $booking), ['status' => 'confirmed']);

        $notification = $mechanic->notifications()->sole();
        $this->assertSame(MechanicJobAssigned::class, $notification->type);
        $this->assertSame('Pekerjaan Servis Baru', $notification->data['title']);

        $this->actingAs($mechanic)->get(route('mechanic.notifications.index'))->assertOk()->assertSee('Pekerjaan Servis Baru');
        $this->get(route('mechanic.notifications.open', $notification->id))->assertRedirect(route('mechanic.jobs.show', $booking));
        $this->get(route('account.notifications.index'))->assertRedirect(route('mechanic.notifications.index'));
        $this->get(route('mechanic.services.index'))->assertOk()->assertSee('Ban &amp; Aki', false)->assertSee('Cek tekanan ban');

        $other = User::factory()->mechanic()->create();
        $this->actingAs($this->users['admin'])->patch(route('admin.service-bookings.assign-mechanic', $booking), ['mechanic_id' => $other->id]);
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_role_label_baru(): void
    {
        $this->assertSame('Admin', $this->users['admin']->roleLabel());
        $this->assertSame('Customer Service', $this->users['customer_service']->roleLabel());
        $this->assertSame('Owner', $this->users['owner']->roleLabel());
        $this->assertSame('Sales', $this->users['sales']->roleLabel());
        $this->assertSame('Mekanik', $this->users['mechanic']->roleLabel());
    }
}
