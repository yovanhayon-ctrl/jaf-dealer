<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Katalog publik suku cadang & aksesori (/spare-part, /spare-part/{slug}).
 */
class SparePartController extends Controller
{
    public const PER_PAGE = 12;

    public const SORTS = [
        'terbaru' => ['created_at', 'desc'],
        'termurah' => ['price', 'asc'],
        'termahal' => ['price', 'desc'],
        'nama_asc' => ['name', 'asc'],
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $category = array_key_exists((string) $request->query('kategori'), SparePart::CATEGORIES)
            ? $request->query('kategori')
            : null;
        $inStockOnly = $request->boolean('tersedia');
        $sortKey = array_key_exists((string) $request->query('urut'), self::SORTS)
            ? $request->query('urut')
            : 'terbaru';
        [$sortColumn, $sortDirection] = self::SORTS[$sortKey];

        // Hitung jumlah spare part aktif per kategori untuk filter badge
        $categoryCounts = SparePart::query()
            ->active()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->all();

        $totalActive = array_sum($categoryCounts);

        $spareParts = SparePart::query()
            ->active()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('part_number', 'like', "%{$search}%")
                        ->orWhere('compatibility', 'like', "%{$search}%");
                });
            })
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category))
            ->when($inStockOnly, fn (Builder $query) => $query->where('stock', '>', 0))
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('pages.spare-parts.index', [
            'spareParts' => $spareParts,
            'categories' => SparePart::CATEGORIES,
            'categoryCounts' => $categoryCounts,
            'totalActive' => $totalActive,
            'filters' => [
                'q' => $search,
                'kategori' => $category,
                'tersedia' => $inStockOnly,
                'urut' => $sortKey,
            ],
            'hasFilters' => $search !== '' || $category !== null || $inStockOnly || $sortKey !== 'terbaru',
        ]);
    }

    public function show(SparePart $sparePart): View
    {
        abort_unless($sparePart->is_active, 404);

        $relatedParts = SparePart::query()
            ->active()
            ->whereKeyNot($sparePart->id)
            ->where('category', $sparePart->category)
            ->latest()
            ->limit(4)
            ->get();

        return view('pages.spare-parts.show', [
            'sparePart' => $sparePart,
            'relatedParts' => $relatedParts,
            'whatsappUrl' => $sparePart->whatsappOrderUrl(),
        ]);
    }
}
