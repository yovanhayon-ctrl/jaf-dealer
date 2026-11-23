<?php

namespace App\Http\Controllers\Mechanic;

use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use App\Models\ServiceBookingTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel mekanik: informasi servis yang ditugaskan dan checklist pekerjaan (read-only).
 * Seluruh aksi status, checklist, dan temuan dikelola oleh Admin.
 * Booking milik mekanik lain: 404.
 */
class JobController extends Controller
{
    /** Riwayat servis selesai yang ditampilkan di bawah daftar aktif. */
    public const RECENT_COMPLETED = 10;

    public function index(Request $request): View
    {
        $jobs = ServiceBooking::query()
            ->where('mechanic_id', $request->user()->id)
            ->with(['service:id,name', 'tasks:id,service_booking_id,status'])
            ->whereIn('status', [ServiceBooking::STATUS_CONFIRMED, ServiceBooking::STATUS_IN_PROGRESS])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get();

        $completed = ServiceBooking::query()
            ->where('mechanic_id', $request->user()->id)
            ->where('status', ServiceBooking::STATUS_COMPLETED)
            ->with('service:id,name')
            ->latest('finished_at')
            ->limit(self::RECENT_COMPLETED)
            ->get();

        return view('mechanic.jobs.index', [
            'inProgress' => $jobs->where('status', ServiceBooking::STATUS_IN_PROGRESS),
            'upcoming' => $jobs->where('status', ServiceBooking::STATUS_CONFIRMED),
            'completed' => $completed,
        ]);
    }

    public function show(Request $request, ServiceBooking $serviceBooking): View
    {
        $this->ensureAssigned($request, $serviceBooking);

        $serviceBooking->load(['service:id,name,duration_minutes', 'tasks', 'user:id,name']);

        return view('mechanic.jobs.show', ['booking' => $serviceBooking]);
    }

    public function start(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $this->ensureAssigned($request, $serviceBooking);

        return $this->backToJob($serviceBooking)->with('error', 'Status pengerjaan servis dikelola oleh Admin.');
    }

    public function finish(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $this->ensureAssigned($request, $serviceBooking);

        return $this->backToJob($serviceBooking)->with('error', 'Penyelesaian servis dikelola oleh Admin.');
    }

    /**
     * Checklist pekerjaan hanya dapat dilakukan oleh Admin.
     */
    public function updateTask(Request $request, ServiceBooking $serviceBooking, ServiceBookingTask $task): RedirectResponse
    {
        $this->ensureAssigned($request, $serviceBooking);

        return $this->backToJob($serviceBooking)->with('error', 'Checklist pekerjaan hanya dapat dilakukan oleh Admin.');
    }

    /**
     * Temuan dan rekomendasi dikelola oleh Admin.
     */
    public function updateFindings(Request $request, ServiceBooking $serviceBooking): RedirectResponse
    {
        $this->ensureAssigned($request, $serviceBooking);

        return $this->backToJob($serviceBooking)->with('error', 'Temuan dan rekomendasi dikelola oleh Admin.');
    }

    private function ensureAssigned(Request $request, ServiceBooking $serviceBooking): void
    {
        abort_unless($serviceBooking->isAssignedTo($request->user()), 404);
    }

    private function backToJob(ServiceBooking $serviceBooking): RedirectResponse
    {
        return redirect()->route('mechanic.jobs.show', $serviceBooking);
    }
}
