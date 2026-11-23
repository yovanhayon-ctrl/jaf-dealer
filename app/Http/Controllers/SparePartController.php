<?php

namespace App\Http\Controllers;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Katalog publik suku cadang & aksesori (/spare-part, /spare-part/{slug}).
 */
class SparePartController extends Controller
{
    public const PER_PAGE = 12;

    public const SORTS = [
        'terbaru' => ['Terbaru', 'created_at', 'desc'],
        'termurah' => ['Harga: Rendah ke Tinggi', 'price', 'asc'],
        'termahal' => ['Harga: Tinggi ke Rendah', 'price', 'desc'],
        'nama_asc' => ['Nama: A ke Z', 'name', 'asc'],
    ];

    public const CAR_MODELS = [
        'universal' => [
            'label' => 'Universal / Semua Mobil',
            'keywords' => ['universal', 'semua'],
        ],
        'livina' => [
            'label' => 'Livina / Grand Livina',
            'keywords' => ['livina', 'grand livina'],
        ],
        'xtrail' => [
            'label' => 'X-Trail',
            'keywords' => ['x-trail', 'xtrail'],
        ],
        'serena' => [
            'label' => 'Serena',
            'keywords' => ['serena'],
        ],
        'juke' => [
            'label' => 'Juke',
            'keywords' => ['juke'],
        ],
        'magnite' => [
            'label' => 'Magnite',
            'keywords' => ['magnite'],
        ],
        'kicks' => [
            'label' => 'Kicks e-Power',
            'keywords' => ['kicks'],
        ],
        'navara' => [
            'label' => 'Navara',
            'keywords' => ['navara'],
        ],
        'sport' => [
            'label' => 'GT-R / 350Z / Sport',
            'keywords' => ['gt-r', 'gtr', '350z', '370z', 'sport'],
        ],
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $category = array_key_exists((string) $request->query('kategori'), SparePart::CATEGORIES)
            ? (string) $request->query('kategori')
            : null;
        $model = array_key_exists((string) $request->query('model'), self::CAR_MODELS)
            ? (string) $request->query('model')
            : null;
        $inStockOnly = $request->boolean('tersedia');

        $minPrice = $request->filled('harga_min')
            ? (int) preg_replace('/\D/', '', (string) $request->query('harga_min'))
            : null;
        $maxPrice = $request->filled('harga_max')
            ? (int) preg_replace('/\D/', '', (string) $request->query('harga_max'))
            : null;

        $sortKey = array_key_exists((string) $request->query('urut'), self::SORTS)
            ? (string) $request->query('urut')
            : 'terbaru';
        [, $sortColumn, $sortDirection] = self::SORTS[$sortKey];

        $query = SparePart::query()
            ->active()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('part_number', 'like', "%{$search}%")
                        ->orWhere('compatibility', 'like', "%{$search}%");
                });
            })
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category))
            ->when($model !== null, function (Builder $query) use ($model) {
                $keywords = self::CAR_MODELS[$model]['keywords'];
                $query->where(function (Builder $sub) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $sub->orWhere('compatibility', 'like', "%{$kw}%")
                            ->orWhere('description', 'like', "%{$kw}%")
                            ->orWhere('name', 'like', "%{$kw}%");
                    }
                });
            })
            ->when($minPrice !== null && $minPrice > 0, fn (Builder $query) => $query->where('price', '>=', $minPrice))
            ->when($maxPrice !== null && $maxPrice > 0, fn (Builder $query) => $query->where('price', '<=', $maxPrice))
            ->when($inStockOnly, fn (Builder $query) => $query->where('stock', '>', 0))
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id');

        $spareParts = $query->paginate(self::PER_PAGE)->withQueryString();

        $filters = [
            'q' => $search,
            'kategori' => $category,
            'model' => $model,
            'harga_min' => $minPrice,
            'harga_max' => $maxPrice,
            'tersedia' => $inStockOnly,
            'urut' => $sortKey,
        ];

        // Query aktif untuk chip dan sorting
        $activeQuery = array_filter([
            'q' => $search !== '' ? $search : null,
            'kategori' => $category,
            'model' => $model,
            'harga_min' => $minPrice,
            'harga_max' => $maxPrice,
            'tersedia' => $inStockOnly ? '1' : null,
            'urut' => $sortKey !== 'terbaru' ? $sortKey : null,
        ]);

        $sortQuery = Arr::except($activeQuery, ['urut']);

        $chips = $this->buildActiveChips($search, $category, $model, $minPrice, $maxPrice, $inStockOnly, $activeQuery);

        $hasFilters = $search !== '' || $category !== null || $model !== null || $minPrice !== null || $maxPrice !== null || $inStockOnly;

        return view('pages.spare-parts.index', [
            'spareParts' => $spareParts,
            'categories' => SparePart::CATEGORIES,
            'carModels' => self::CAR_MODELS,
            'filters' => $filters,
            'chips' => $chips,
            'sortQuery' => $sortQuery,
            'hasFilters' => $hasFilters,
        ]);
    }

    /**
     * @param  array<string, mixed>  $activeQuery
     * @return array<int, array{label: string, url: string}>
     */
    private function buildActiveChips(?string $search, ?string $category, ?string $model, ?int $minPrice, ?int $maxPrice, bool $inStockOnly, array $activeQuery): array
    {
        $rupiah = fn (int $amount) => 'Rp '.number_format($amount, 0, ',', '.');
        $chips = [];

        if ($search !== null && $search !== '') {
            $chips[] = [
                'label' => "“{$search}”",
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['q'])),
            ];
        }

        if ($category !== null && isset(SparePart::CATEGORIES[$category])) {
            $chips[] = [
                'label' => SparePart::CATEGORIES[$category],
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['kategori'])),
            ];
        }

        if ($model !== null && isset(self::CAR_MODELS[$model])) {
            $chips[] = [
                'label' => 'Mobil: '.self::CAR_MODELS[$model]['label'],
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['model'])),
            ];
        }

        if ($minPrice !== null && $maxPrice !== null) {
            $chips[] = [
                'label' => "Harga {$rupiah($minPrice)} – {$rupiah($maxPrice)}",
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['harga_min', 'harga_max'])),
            ];
        } elseif ($minPrice !== null) {
            $chips[] = [
                'label' => "Harga ≥ {$rupiah($minPrice)}",
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['harga_min'])),
            ];
        } elseif ($maxPrice !== null) {
            $chips[] = [
                'label' => "Harga ≤ {$rupiah($maxPrice)}",
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['harga_max'])),
            ];
        }

        if ($inStockOnly) {
            $chips[] = [
                'label' => 'Hanya stok ada',
                'url' => route('spare-parts.index', Arr::except($activeQuery, ['tersedia'])),
            ];
        }

        return $chips;
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

        $waNumber = preg_replace('/\D/', '', (string) config('dealer.whatsapp'));
        $waBaseUrl = $waNumber !== '' ? "https://wa.me/{$waNumber}" : null;

        return view('pages.spare-parts.show', [
            'sparePart' => $sparePart,
            'relatedParts' => $relatedParts,
            'whatsappUrl' => $sparePart->whatsappOrderUrl(),
            'waBaseUrl' => $waBaseUrl,
        ]);
    }
}
