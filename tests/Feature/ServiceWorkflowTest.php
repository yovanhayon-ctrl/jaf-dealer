<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingTask;
use App\Models\User;
use App\Notifications\MechanicJobAssigned;
use App\Notifications\ServiceBookingStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Penugasan mekanik & checklist pekerjaan servis: admin menugaskan, mekanik mengerjakan, customer memantau.
 */
class ServiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mechanic;

    private User $otherMechanic;

    private User $customer;

    private Service $service;

    private ServiceBooking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-13 09:00:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->admin()->create();
        $this->mechanic = User::factory()->mechanic()->create(['name' => 'Joko Prasetyo']);
        $this->otherMechanic = User::factory()->mechanic()->create(['name' => 'Wayan Sudarma']);
        $this->customer = User::factory()->create(['name' => 'Budi Santoso']);
        $this->service = Service::factory()->create([
            'name' => 'Servis Berkala',
            'checklist' => "Ganti oli mesin\nGanti filter oli\nCek dan setel rem",
        ]);
        $this->booking = ServiceBooking::factory()->recycle($this->customer)->recycle($this->service)->create([
            'status' => ServiceBooking::STATUS_CONFIRMED,
            'preferred_date' => '2026-10-13',
            'preferred_time' => '10:00',
            'plate_number' => 'B 1234 ABC',
            'vehicle_model' => 'Nissan Livina',
        ]);
    }

    private function assign(?User $mechanic = null): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.service-bookings.assign-mechanic', $this->booking), ['mechanic_id' => ($mechanic ?? $this->mechanic)->id])
            ->assertSessionHasNoErrors();
    }

    private function task(string $name): ServiceBookingTask
    {
        return $this->booking->tasks()->where('name', $name)->sole();
    }

    public function test_admin_mengisi_daftar_pekerjaan_layanan(): void
    {
        $this->actingAs($this->admin)->put(route('admin.services.update', $this->service), [
            'name' => 'Servis Berkala', 'checklist' => "- Ganti oli mesin\n\n• Cek aki\nCek aki\n  Cek   tekanan ban ",
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Ganti oli mesin', 'Cek aki', 'Cek tekanan ban'], $this->service->fresh()->checklistItems());

        $this->put(route('admin.services.update', $this->service), [
            'name' => 'Servis Berkala', 'checklist' => implode("\n", array_map(fn ($i) => "Pekerjaan {$i}", range(1, 31))),
        ])->assertSessionHasErrors(['checklist' => 'Daftar pekerjaan maksimal 30 baris.']);
    }

    public function test_admin_menugaskan_mekanik_dan_checklist_tersalin_sekali(): void
    {
        $this->assign();

        $this->booking->refresh();
        $this->assertTrue($this->booking->isAssignedTo($this->mechanic));
        $this->assertSame(['Ganti oli mesin', 'Ganti filter oli', 'Cek dan setel rem'], $this->booking->tasks->pluck('name')->all());

        // Ganti mekanik: checklist tidak digandakan.
        $this->assign($this->otherMechanic);
        $this->assertSame(3, $this->booking->tasks()->count());
        $this->assertTrue($this->booking->fresh()->isAssignedTo($this->otherMechanic));

        $this->get(route('admin.service-bookings.index'))->assertSee('Mekanik: Wayan Sudarma');
    }

    public function test_hanya_mekanik_aktif_yang_bisa_ditugaskan(): void
    {
        $inactive = User::factory()->mechanic()->inactive()->create();

        foreach ([$inactive, $this->admin, $this->customer] as $user) {
            $this->actingAs($this->admin)
                ->patch(route('admin.service-bookings.assign-mechanic', $this->booking), ['mechanic_id' => $user->id])
                ->assertSessionHasErrorsIn('mechanic', 'mechanic_id');
        }

        $this->booking->update(['status' => ServiceBooking::STATUS_COMPLETED]);
        $this->patch(route('admin.service-bookings.assign-mechanic', $this->booking), ['mechanic_id' => $this->mechanic->id])
            ->assertSessionHas('error');
        $this->assertNull($this->booking->fresh()->mechanic_id);
    }

    public function test_admin_menambah_dan_menghapus_pekerjaan(): void
    {
        $this->assign();

        $this->post(route('admin.service-bookings.tasks.store', $this->booking), ['name' => 'Cek bunyi roda depan'])
            ->assertSessionHasNoErrors();
        $this->assertSame(4, $this->task('Cek bunyi roda depan')->position);

        $this->delete(route('admin.service-bookings.tasks.destroy', [$this->booking, $this->task('Ganti filter oli')]))
            ->assertSessionHas('success');
        $this->assertSame(3, $this->booking->tasks()->count());

        // Pekerjaan yang sudah dikerjakan tidak bisa dihapus.
        $done = $this->task('Ganti oli mesin');
        $done->update(['status' => ServiceBookingTask::STATUS_DONE]);
        $this->delete(route('admin.service-bookings.tasks.destroy', [$this->booking, $done]))->assertSessionHas('error');
        $this->assertModelExists($done);
    }

    public function test_mekanik_hanya_melihat_pekerjaannya_sendiri(): void
    {
        $this->assign();

        $this->actingAs($this->mechanic)->get(route('mechanic.jobs.index'))
            ->assertOk()
            ->assertSee('B 1234 ABC')
            ->assertSee('0 dari 3 pekerjaan ditangani');
        $this->get(route('mechanic.jobs.show', $this->booking))->assertOk()->assertSee('Ganti oli mesin');

        $this->actingAs($this->otherMechanic)->get(route('mechanic.jobs.index'))->assertDontSee('B 1234 ABC');
        $this->get(route('mechanic.jobs.show', $this->booking))->assertNotFound();
        $this->patch(route('mechanic.jobs.start', $this->booking))->assertNotFound();
        $this->patch(route('mechanic.jobs.tasks.update', [$this->booking, $this->task('Ganti oli mesin')]), ['status' => 'done'])->assertNotFound();

        $this->actingAs($this->admin)->get(route('mechanic.jobs.show', $this->booking))->assertForbidden();
    }

    public function test_alur_admin_kelola_checklist_dan_mekanik_mendapat_notifikasi_selesai(): void
    {
        Notification::fake();
        $this->assign();

        // Mekanik mencoba aksi ditolak karena dikelola admin
        $this->actingAs($this->mechanic);
        $this->patch(route('mechanic.jobs.tasks.update', [$this->booking, $this->task('Ganti oli mesin')]), ['status' => 'done'])
            ->assertSessionHas('error', 'Checklist pekerjaan hanya dapat dilakukan oleh Admin.');
        $this->patch(route('mechanic.jobs.start', $this->booking))
            ->assertSessionHas('error', 'Status pengerjaan servis dikelola oleh Admin.');
        $this->patch(route('mechanic.jobs.finish', $this->booking))
            ->assertSessionHas('error', 'Penyelesaian servis dikelola oleh Admin.');

        // Admin mulai pengerjaan
        $this->actingAs($this->admin);
        $this->patch(route('admin.service-bookings.update-status', $this->booking), ['status' => ServiceBooking::STATUS_IN_PROGRESS])
            ->assertSessionHas('success');
        $this->booking->refresh();
        $this->assertSame(ServiceBooking::STATUS_IN_PROGRESS, $this->booking->status);
        $this->assertNotNull($this->booking->started_at);

        // Admin mencentang checklist
        $this->patch(route('admin.service-bookings.tasks.update', [$this->booking, $this->task('Ganti oli mesin')]), ['status' => 'done']);
        $this->patch(route('admin.service-bookings.tasks.update', [$this->booking, $this->task('Ganti filter oli')]), [
            'status' => 'skipped', 'note' => 'Filter oli belum tersedia',
        ]);
        $this->patch(route('admin.service-bookings.tasks.update', [$this->booking, $this->task('Cek dan setel rem')]), ['status' => 'done']);
        $this->patch(route('admin.service-bookings.findings', $this->booking), ['findings' => 'Kampas rem depan tinggal 20%, disarankan ganti bulan depan.'])
            ->assertSessionHas('success');

        // Admin menyelesaikan servis
        $this->patch(route('admin.service-bookings.update-status', $this->booking), ['status' => ServiceBooking::STATUS_COMPLETED])
            ->assertSessionHas('success');

        $this->booking->refresh();
        $this->assertSame(ServiceBooking::STATUS_COMPLETED, $this->booking->status);
        $this->assertNotNull($this->booking->finished_at);
        $this->assertSame([3, 3], $this->booking->taskProgress());
        $this->assertSame('Filter oli belum tersedia', $this->task('Ganti filter oli')->note);

        // Notifikasi ke customer dan ke mekanik saat selesai
        Notification::assertSentTo($this->customer, ServiceBookingStatusChanged::class);
        Notification::assertSentTo($this->mechanic, MechanicJobAssigned::class, function ($notification) {
            return $notification->toArray($this->mechanic)['title'] === 'Pekerjaan Servis Selesai';
        });

        // Mekanik dapat melihat daftar pekerjaan & temuan secara read-only
        $this->actingAs($this->mechanic)->get(route('mechanic.jobs.show', $this->booking))
            ->assertOk()
            ->assertSee('Ganti oli mesin')
            ->assertSee('Kampas rem depan tinggal 20%')
            ->assertDontSee('form method="POST" action="'.route('mechanic.jobs.start', $this->booking), false);
    }

    public function test_aksi_mulai_oleh_mekanik_ditolak(): void
    {
        $this->assign();
        $this->actingAs($this->mechanic)->patch(route('mechanic.jobs.start', $this->booking))
            ->assertSessionHas('error', 'Status pengerjaan servis dikelola oleh Admin.');
    }

    public function test_status_admin_tetap_mencatat_waktu_dan_memberi_tahu_customer(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->patch(route('admin.service-bookings.update-status', $this->booking), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->booking->fresh()->started_at);
        Notification::assertSentTo($this->customer, ServiceBookingStatusChanged::class);
    }
}
