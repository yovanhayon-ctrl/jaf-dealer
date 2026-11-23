<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MechanicRating;
use App\Models\Testimonial;
use App\Notifications\Notifier;
use App\Notifications\TestimonialModerated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderasi & kelola ulasan customer: ulasan pembelian mobil dan ulasan servis mekanik.
 */
class TestimonialController extends Controller
{
    public const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $tipe = $request->query('tipe') === 'servis' ? 'servis' : 'mobil';

        $totalCarTestimonials = Testimonial::count();
        $totalServiceRatings = MechanicRating::count();

        if ($tipe === 'servis') {
            $rating = in_array((int) $request->query('rating'), [1, 2, 3, 4, 5], true)
                ? (int) $request->query('rating')
                : null;

            $ratings = MechanicRating::query()
                ->with([
                    'user:id,name,email',
                    'mechanic:id,name',
                    'booking:id,service_id,plate_number,vehicle_model',
                    'booking.service:id,name',
                ])
                ->when($rating !== null, fn ($query) => $query->where('rating', $rating))
                ->latest()
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            return view('admin.testimonials.index', [
                'tipe' => $tipe,
                'mechanicRatings' => $ratings,
                'selectedRating' => $rating,
                'ratingCounts' => MechanicRating::query()->selectRaw('rating, COUNT(*) AS total')->groupBy('rating')->pluck('total', 'rating'),
                'totalCarTestimonials' => $totalCarTestimonials,
                'totalServiceRatings' => $totalServiceRatings,
            ]);
        }

        $status = in_array($request->query('status'), Testimonial::STATUSES, true) ? $request->query('status') : null;

        $testimonials = Testimonial::query()
            ->with([
                'user:id,name,email',
                'purchaseRequest:id,car_id',
                'purchaseRequest.car:id,brand_id,name,year',
                'purchaseRequest.car.brand:id,name',
            ])
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.testimonials.index', [
            'tipe' => $tipe,
            'testimonials' => $testimonials,
            'status' => $status,
            'counts' => Testimonial::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'totalCarTestimonials' => $totalCarTestimonials,
            'totalServiceRatings' => $totalServiceRatings,
        ]);
    }

    public function approve(Testimonial $testimonial): RedirectResponse
    {
        if ($testimonial->isApproved()) {
            return back()->with('status', 'Ulasan ini sudah disetujui.');
        }

        $testimonial->update(['status' => Testimonial::STATUS_APPROVED, 'admin_note' => null, 'approved_at' => now()]);
        Notifier::send($testimonial->user, new TestimonialModerated($testimonial));

        return back()->with('success', 'Ulasan disetujui dan kini tampil di beranda.');
    }

    public function reject(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $validated = $request->validateWithBag(
            "reject{$testimonial->id}",
            ['admin_note' => ['required', 'string', 'min:5', 'max:500']],
            [],
            ['admin_note' => 'alasan penolakan'],
        );

        if ($testimonial->status === Testimonial::STATUS_REJECTED) {
            return back()->with('status', 'Ulasan ini sudah ditolak.');
        }

        $testimonial->update(['status' => Testimonial::STATUS_REJECTED, 'admin_note' => $validated['admin_note'], 'approved_at' => null]);
        Notifier::send($testimonial->user, new TestimonialModerated($testimonial));

        return back()->with('success', 'Ulasan ditolak. Customer diberi tahu alasannya.');
    }
}
