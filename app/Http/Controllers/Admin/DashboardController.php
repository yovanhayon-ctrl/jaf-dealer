<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CreateBackup;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\PurchaseRequest;
use App\Models\ServiceBooking;
use App\Models\TestDrive;
use App\Models\Testimonial;
use App\Models\User;
use App\Reports\DashboardTrend;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isMechanic()) {
            return redirect()->route('mechanic.jobs.index');
        }

        $canSeeBackup = $user->can('backup.manage');

        if ($user->isOwner()) {
            return view('admin.dashboard', [
                'roleView' => 'owner',
                'canSeeBackup' => $canSeeBackup,
                'latestBackup' => $canSeeBackup ? CreateBackup::latest() : null,
                'ownerStats' => $this->ownerStats(),
                'trend' => (new DashboardTrend)->months(),
                'stats' => $this->stats(),
            ]);
        }

        if ($user->isSales()) {
            return view('admin.dashboard', [
                'roleView' => 'sales',
                'canSeeBackup' => false,
                'stats' => $this->stats(),
                'salesStats' => [
                    'pending_test_drives' => TestDrive::where('sales_id', $user->id)->where('status', 'pending')->count(),
                    'pending_purchases' => PurchaseRequest::where('sales_id', $user->id)->where('status', 'pending')->count(),
                ],
                'upcomingTestDrives' => TestDrive::query()
                    ->where('sales_id', $user->id)
                    ->with(['user:id,name', 'car:id,brand_id,name', 'car.brand:id,name'])
                    ->whereBetween('preferred_date', [today(), today()->addDay()])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->orderBy('preferred_date')
                    ->orderBy('preferred_time')
                    ->get(),
                'latestPurchases' => PurchaseRequest::query()
                    ->where('sales_id', $user->id)
                    ->with(['user:id,name', 'car:id,brand_id,name', 'car.brand:id,name'])
                    ->latest()
                    ->limit(5)
                    ->get(),
            ]);
        }

        if ($user->isCustomerService()) {
            return view('admin.dashboard', [
                'roleView' => 'customer_service',
                'canSeeBackup' => false,
                'stats' => $this->stats(),
                'csStats' => [
                    'pending_service_bookings' => ServiceBooking::where('status', ServiceBooking::STATUS_PENDING)->count(),
                    'pending_test_drives' => TestDrive::where('status', 'pending')->count(),
                    'new_testimonials' => Testimonial::where('status', Testimonial::STATUS_PENDING)->count(),
                    'unconfirmed_test_drives_tomorrow' => TestDrive::query()->where('status', TestDrive::STATUS_PENDING)->whereDate('preferred_date', today()->addDay())->count(),
                    'unconfirmed_service_bookings_tomorrow' => ServiceBooking::query()->where('status', ServiceBooking::STATUS_PENDING)->whereDate('preferred_date', today()->addDay())->count(),
                ],
                'upcomingTestDrives' => TestDrive::query()
                    ->with(['user:id,name', 'car:id,brand_id,name', 'car.brand:id,name'])
                    ->whereDate('preferred_date', '>=', today())
                    ->where('status', 'pending')
                    ->orderBy('preferred_date')
                    ->orderBy('preferred_time')
                    ->limit(5)
                    ->get(),
                'upcomingServiceBookings' => ServiceBooking::query()
                    ->with(['user:id,name', 'service:id,name'])
                    ->whereDate('preferred_date', '>=', today())
                    ->where('status', ServiceBooking::STATUS_PENDING)
                    ->orderBy('preferred_date')
                    ->orderBy('preferred_time')
                    ->limit(5)
                    ->get(),
            ]);
        }

        // Super Admin & Admin Dealer: Operasional murni (tanpa angka rupiah!)
        return view('admin.dashboard', [
            'roleView' => 'operational',
            'canSeeBackup' => false,
            'stats' => $this->stats(),
            'latestPurchases' => PurchaseRequest::query()
                ->with(['user:id,name', 'car:id,brand_id,name', 'car.brand:id,name'])
                ->latest()
                ->limit(5)
                ->get(),
            'upcomingTestDrives' => TestDrive::query()
                ->with(['user:id,name', 'car:id,brand_id,name', 'car.brand:id,name'])
                ->whereDate('preferred_date', '>=', today())
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('preferred_date')
                ->orderBy('preferred_time')
                ->limit(5)
                ->get(),
            'upcomingServiceBookings' => ServiceBooking::query()
                ->with(['user:id,name', 'service:id,name'])
                ->whereDate('preferred_date', '>=', today())
                ->active()
                ->orderBy('preferred_date')
                ->orderBy('preferred_time')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Statistik pendapatan khusus Owner (penjualan final + servis selesai).
     *
     * @return array<string, int>
     */
    private function ownerStats(): array
    {
        $currentMonthStart = today()->startOfMonth();
        $currentMonthEnd = today()->endOfMonth();

        $sold = PurchaseRequest::SOLD_STATUSES;

        $salesRow = DB::table('purchase_requests')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->whereIn('status', $sold)
            ->selectRaw('COUNT(*) as units_sold, COALESCE(SUM(car_price), 0) as sales_revenue')
            ->first();

        $serviceRow = DB::table('service_bookings')
            ->whereBetween('preferred_date', [$currentMonthStart, $currentMonthEnd])
            ->where('status', ServiceBooking::STATUS_COMPLETED)
            ->selectRaw('COUNT(*) as services_completed, COALESCE(SUM(service_price), 0) as service_revenue')
            ->first();

        $salesRevenue = (int) ($salesRow->sales_revenue ?? 0);
        $serviceRevenue = (int) ($serviceRow->service_revenue ?? 0);

        return [
            'sales_revenue' => $salesRevenue,
            'service_revenue' => $serviceRevenue,
            'total_revenue' => $salesRevenue + $serviceRevenue,
            'units_sold' => (int) ($salesRow->units_sold ?? 0),
            'services_completed' => (int) ($serviceRow->services_completed ?? 0),
            'out_of_stock_cars' => Car::where('is_active', true)->where('stock', 0)->count(),
        ];
    }

    /**
     * Angka kartu statistik operasional. Mobil aktif & stok habis dihitung dalam satu query agregat.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $cars = Car::toBase()
            ->selectRaw('COUNT(CASE WHEN is_active = 1 THEN 1 END) AS active')
            ->selectRaw('COUNT(CASE WHEN is_active = 1 AND stock = 0 THEN 1 END) AS out_of_stock')
            ->first();

        return [
            'active_cars' => (int) $cars->active,
            'out_of_stock_cars' => (int) $cars->out_of_stock,
            'pending_test_drives' => TestDrive::where('status', 'pending')->count(),
            'pending_purchases' => PurchaseRequest::where('status', 'pending')->count(),
            'pending_service_bookings' => ServiceBooking::where('status', ServiceBooking::STATUS_PENDING)->count(),
            'customers' => User::where('role', User::ROLE_CUSTOMER)->count(),
            // Perlu ditindaklanjuti: lewat batas bayar pertama / jadwal sudah lewat tetapi belum diselesaikan.
            'overdue_payments' => PurchaseRequest::query()->paymentOverdue()->count(),
            'overdue_test_drives' => TestDrive::query()->overdue()->count(),
            'overdue_service_bookings' => ServiceBooking::query()->overdue()->count(),
            'pending_refunds' => PurchaseRequest::query()->refundPending()->count(),
            'awaiting_response' => PurchaseRequest::query()->awaitingResponse()->count(),
            // Jadwal besok yang masih Menunggu: tidak dikirimi pengingat H-1 sebelum dikonfirmasi.
            'unconfirmed_test_drives_tomorrow' => TestDrive::query()->where('status', TestDrive::STATUS_PENDING)->whereDate('preferred_date', today()->addDay())->count(),
            'unconfirmed_service_bookings_tomorrow' => ServiceBooking::query()->where('status', ServiceBooking::STATUS_PENDING)->whereDate('preferred_date', today()->addDay())->count(),
        ];
    }
}
