<?php

namespace App\Http\Controllers;

use App\Actions\BookService;
use App\Http\Requests\ServiceBookingRequest;
use App\Models\Service;
use App\Models\ServiceBooking;
use App\Models\User;
use App\Notifications\Admin\AdminActivityNotification;
use App\Notifications\Admin\ServiceBookingActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Booking servis oleh customer (/servis/booking). Riwayat & pembatalan ada di Account\ServiceBookingController.
 */
class ServiceBookingController extends Controller
{
    /** Batas POST booking per menit per user (rate limiter "service-booking"). */
    public const MAX_BOOKINGS_PER_MINUTE = 5;

    public function create(Request $request): View
    {
        if ($request->user()->isStaff()) {
            return view('pages.service-bookings.create', ['isAdmin' => true]);
        }

        $services = Service::query()->active()->orderBy('id')->get(['id', 'name', 'slug', 'description', 'price_from', 'duration_minutes']);

        // ?layanan=slug dari halaman /servis: pilih otomatis bila layanan aktif.
        $slug = $request->query('layanan');
        $selectedService = is_string($slug) ? $services->firstWhere('slug', $slug) : null;

        // ?part_id=&qty= dari halaman detail suku cadang
        $selectedPart = null;
        $selectedPartQty = 1;
        $prefilledComplaint = null;

        if ($request->filled('part_id')) {
            $selectedPart = \App\Models\SparePart::query()->active()->find($request->query('part_id'));
            if ($selectedPart) {
                $selectedPartQty = max(1, (int) $request->query('qty', 1));
                if ($selectedService === null) {
                    $selectedService = $services->first(fn ($s) => $s->slug === 'suku-cadang' || str_contains(strtolower($s->name), 'suku cadang'))
                        ?? $services->first();
                }
                $subtotalFormatted = 'Rp '.number_format($selectedPart->price * $selectedPartQty, 0, ',', '.');
                $prefilledComplaint = "Pemasangan Suku Cadang: {$selectedPart->name} (SKU: {$selectedPart->part_number}) sebanyak {$selectedPartQty} {$selectedPart->unit} — Estimasi Part: {$subtotalFormatted}.";
            }
        }

        $userVehicles = collect();
        if ($request->user()) {
            // Dari purchase requests (mobil yang dibeli customer di dealer)
            $purchases = \App\Models\PurchaseRequest::query()
                ->where('user_id', $request->user()->id)
                ->with('car.brand')
                ->latest('id')
                ->get();

            foreach ($purchases as $p) {
                if ($p->car) {
                    $model = trim("{$p->car->brand->name} {$p->car->name}");
                    $userVehicles->push([
                        'vehicle_model' => $model,
                        'plate_number' => '',
                        'vehicle_year' => $p->car->year ? (string) $p->car->year : '',
                        'mileage' => $p->car->mileage ? (string) $p->car->mileage : '',
                        'label' => "{$model} ({$p->car->year}) · Pembelian Dealer",
                    ]);
                }
            }

            // Dari riwayat servis sebelumnya (mobil yang sudah pernah diservis)
            $pastBookings = ServiceBooking::query()
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->get(['vehicle_model', 'plate_number', 'vehicle_year', 'mileage']);

            foreach ($pastBookings as $b) {
                $userVehicles->push([
                    'vehicle_model' => $b->vehicle_model,
                    'plate_number' => $b->plate_number,
                    'vehicle_year' => (string) ($b->vehicle_year ?? ''),
                    'mileage' => (string) ($b->mileage ?? ''),
                    'label' => "{$b->vehicle_model} [{$b->plate_number}]",
                ]);
            }

            $userVehicles = $userVehicles->unique(function ($item) {
                return $item['plate_number'] ? strtoupper($item['plate_number']) : $item['vehicle_model'];
            })->values();
        }

        $busyMechanicIds = ServiceBooking::query()
            ->active()
            ->whereNotNull('mechanic_id')
            ->pluck('mechanic_id')
            ->unique()
            ->values()
            ->all();

        $mechanics = User::activeMechanics()
            ->get(['id', 'name'])
            ->mapWithKeys(function ($m) use ($busyMechanicIds) {
                $isBusy = in_array($m->id, $busyMechanicIds, true);
                $label = $isBusy ? "{$m->name} (Sedang ada pengerjaan)" : $m->name;

                return [$m->id => $label];
            })
            ->all();

        return view('pages.service-bookings.create', [
            'isAdmin' => false,
            'services' => $services,
            'userVehicles' => $userVehicles,
            'selectedService' => $selectedService,
            'selectedPart' => $selectedPart,
            'selectedPartQty' => $selectedPartQty,
            'prefilledComplaint' => $prefilledComplaint,
            'mechanics' => $mechanics,
            'busyMechanicIds' => $busyMechanicIds,
            'firstDate' => ServiceBookingRequest::firstDate(),
            'lastDate' => ServiceBookingRequest::lastDate(),
            'todayDate' => today()->toDateString(),
            'availableTodaySlots' => ServiceBooking::availableTimeSlots(today()->toDateString()),
        ]);
    }

    public function store(ServiceBookingRequest $request, BookService $bookService): RedirectResponse
    {
        $booking = $bookService->handle($request->user(), $request->bookingData());
        $booking->load(['service:id,name', 'mechanic:id,name']);
        AdminActivityNotification::notifyAdmins(new ServiceBookingActivity($booking));

        return redirect()->route('account.service-bookings.index')->with('success', sprintf(
            'Booking %s untuk %s pada %s pukul %s WIB berhasil dikirim. Tunggu konfirmasi dari dealer.',
            $booking->service->name,
            $booking->plate_number,
            $booking->preferred_date->translatedFormat('d M Y'),
            $booking->timeLabel(),
        ));
    }
}
