<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable([
    'brand_id', 'category_id', 'name', 'slug', 'vehicle_condition', 'year', 'mileage',
    'tax_status', 'document_status', 'ownership_history', 'plate_type', 'warranty_info',
    'price', 'transmission', 'fuel_type', 'engine_cc', 'seats', 'color', 'color_palette', 'stock',
    'description', 'is_active', 'is_featured',
])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory, HasUniqueSlug;

    /** Tahun produksi terlama yang boleh diinput (koleksi heritage & klasik Jepang, mis. AE86 1986). */
    public const MIN_YEAR = 1950;

    public const CONDITION_NEW = 'baru';

    public const CONDITION_USED = 'bekas';

    /** Nilai enum => label bahasa Indonesia. */
    public const CONDITIONS = [
        self::CONDITION_NEW => 'Baru',
        self::CONDITION_USED => 'Bekas',
    ];

    public const TRANSMISSIONS = [
        'manual' => 'Manual',
        'automatic' => 'Otomatis',
    ];

    public const FUEL_TYPES = [
        'bensin' => 'Bensin',
        'diesel' => 'Diesel',
        'hybrid' => 'Hybrid',
        'listrik' => 'Listrik',
    ];

    /**
     * Relasi yang membuat mobil tidak boleh dihapus (test drive & pengajuan: FK restrict;
     * promo: FK nullOnDelete akan mengubah promo khusus mobil menjadi promo umum).
     */
    public const DELETION_BLOCKERS = ['testDrives', 'purchaseRequests', 'promos'];

    /** Batas "stok menipis" di laporan admin (mobil aktif dengan stok ≤ nilai ini). */
    public const LOW_STOCK_THRESHOLD = 1;

    /** Jumlah maksimal mobil unggulan yang bergantian di beranda. */
    public const MAX_FEATURED_ON_HOME = 5;

    /** Relasi yang dipakai x-car-card; di-eager load agar tidak terjadi N+1. */
    public const CARD_RELATIONS = [
        'brand:id,name,slug', 'category:id,name,slug', 'primaryImage:id,car_id,path,thumb_path,color_name',
        'activePromos:id,car_id,discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'price' => 'integer',
            'engine_cc' => 'integer',
            'seats' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'color_palette' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(CarImage::class)->where('is_primary', true);
    }

    public function promos(): HasMany
    {
        return $this->hasMany(Promo::class);
    }

    public function activePromos(): HasMany
    {
        return $this->promos()->active();
    }

    public function testDrives(): HasMany
    {
        return $this->hasMany(TestDrive::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Mobil unggulan yang bisa tampil di beranda: dicentang admin, aktif, dan punya foto.
     */
    #[Scope]
    protected function featuredOnHome(Builder $query): void
    {
        $query->where('is_active', true)->where('is_featured', true)->whereHas('primaryImage');
    }

    /**
     * Mobil yang boleh di-test drive: aktif, dan baru (stok 0 tetap boleh) atau bekas dengan stok.
     * Aturannya sama dengan canBeTestDriven().
     */
    #[Scope]
    protected function testDrivable(Builder $query): void
    {
        $query->where('cars.is_active', true)
            ->where(fn (Builder $query) => $query
                ->where('cars.vehicle_condition', self::CONDITION_NEW)
                ->orWhere('cars.stock', '>', 0));
    }

    /**
     * Tambah kolom `final_price` (harga setelah diskon promo aktif terbesar) yang dihitung di SQL,
     * untuk urutan harga di katalog. Aturannya sama dengan bestActivePromo().
     */
    #[Scope]
    protected function withFinalPrice(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('cars.*');
        }

        [$sql, $bindings] = self::finalPriceSql();
        $query->selectRaw("{$sql} as final_price", $bindings);
    }

    /**
     * Filter menurut harga setelah promo, mis. whereFinalPrice('>=', 100_000_000).
     */
    #[Scope]
    protected function whereFinalPrice(Builder $query, string $operator, int $amount): void
    {
        if (! in_array($operator, ['<', '<=', '=', '>=', '>'], true)) {
            throw new InvalidArgumentException("Operator {$operator} tidak didukung.");
        }

        [$sql, $bindings] = self::finalPriceSql();
        $query->whereRaw("{$sql} {$operator} ?", [...$bindings, $amount]);
    }

    /**
     * Ekspresi SQL harga setelah promo: harga − diskon promo aktif terbesar (diskon ≥ harga diabaikan).
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function finalPriceSql(): array
    {
        $discount = Promo::query()
            ->selectRaw('MAX(promos.discount_amount)')
            ->active()
            ->whereColumn('promos.car_id', 'cars.id')
            ->where('promos.discount_amount', '>', 0)
            ->whereColumn('promos.discount_amount', '<', 'cars.price');

        return ['(cars.price - COALESCE(('.$discount->toSql().'), 0))', $discount->getBindings()];
    }

    protected function conditionLabel(): Attribute
    {
        return Attribute::get(fn () => self::CONDITIONS[$this->vehicle_condition] ?? $this->vehicle_condition);
    }

    protected function transmissionLabel(): Attribute
    {
        return Attribute::get(fn () => self::TRANSMISSIONS[$this->transmission] ?? $this->transmission);
    }

    protected function fuelTypeLabel(): Attribute
    {
        return Attribute::get(fn () => self::FUEL_TYPES[$this->fuel_type] ?? $this->fuel_type);
    }

    /**
     * Ringkasan relasi penghalang hapus, mis. "2 test drive dan 1 pengajuan"; null jika boleh dihapus.
     * Memerlukan withCount/loadCount(self::DELETION_BLOCKERS).
     */
    public function deletionBlockers(): ?string
    {
        $parts = array_values(array_filter([
            $this->test_drives_count ? "{$this->test_drives_count} test drive" : null,
            $this->purchase_requests_count ? "{$this->purchase_requests_count} pengajuan" : null,
            $this->promos_count ? "{$this->promos_count} promo" : null,
        ]));

        if ($parts === []) {
            return null;
        }

        $last = array_pop($parts);

        return $parts === [] ? $last : implode(', ', $parts).' dan '.$last;
    }

    public function isNew(): bool
    {
        return $this->vehicle_condition === self::CONDITION_NEW;
    }

    public function isUsed(): bool
    {
        return $this->vehicle_condition === self::CONDITION_USED;
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Boleh diajukan pembelian: mobil aktif dan stok tersedia (RANCANGAN §5).
     */
    public function canBePurchased(): bool
    {
        return $this->is_active && $this->inStock();
    }

    /**
     * Boleh test drive: mobil baru stok 0 tetap boleh (unit display), mobil bekas stok 0 tidak (sudah terjual).
     */
    public function canBeTestDriven(): bool
    {
        return $this->is_active && ($this->isNew() || $this->inStock());
    }

    /**
     * Promo aktif dengan diskon terbesar. Memerlukan eager load `activePromos`
     * (preventLazyLoading melempar exception bila lupa, supaya tidak terjadi N+1).
     * Diskon yang tidak lebih kecil dari harga saat ini diabaikan (mis. harga mobil diturunkan setelah promo dibuat).
     */
    public function bestActivePromo(): ?Promo
    {
        return $this->activePromos
            ->filter(fn (Promo $promo) => $promo->discount_amount > 0 && $promo->discount_amount < $this->price)
            ->sortByDesc('discount_amount')
            ->first();
    }

    public function promoDiscount(): int
    {
        return $this->bestActivePromo()?->discount_amount ?? 0;
    }

    /**
     * Harga setelah diskon promo aktif terbesar (dipakai katalog & pengajuan).
     */
    public function finalPrice(): int
    {
        return $this->price - $this->promoDiscount();
    }

    /**
     * Biaya tambahan untuk varian warna tertentu (misal: cat khusus heritage/metallic).
     */
    public function colorExtraPrice(?string $colorName): int
    {
        if ($colorName === null || $colorName === '') {
            return 0;
        }

        foreach ($this->colorSwatches() as $swatch) {
            if (strcasecmp($swatch['name'], $colorName) === 0) {
                return (int) ($swatch['extra_price'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Harga mobil disesuaikan dengan biaya tambahan warna (sebelum diskon promo).
     */
    public function priceForColor(?string $colorName): int
    {
        return $this->price + $this->colorExtraPrice($colorName);
    }

    /**
     * Harga final mobil untuk warna tertentu (setelah dipotong diskon promo aktif).
     */
    public function finalPriceForColor(?string $colorName): int
    {
        return max(0, $this->priceForColor($colorName) - $this->promoDiscount());
    }

    public function hasPromoPrice(): bool
    {
        return $this->promoDiscount() > 0;
    }

    /**
     * Daftar varian warna untuk galeri mobil (bisa kustom per mobil atau bawaan model).
     *
     * @return list<array{name: string, hex: string, stock: int, extra_price: int}>
     */
    public function colorSwatches(): array
    {
        if (! empty($this->color_palette) && is_array($this->color_palette)) {
            return array_values(array_map(function ($item) {
                return [
                    'name' => (string) ($item['name'] ?? ''),
                    'hex' => (string) ($item['hex'] ?? '#000000'),
                    'stock' => isset($item['stock']) ? (int) $item['stock'] : ($this->stock > 0 ? 1 : 0),
                    'extra_price' => max(0, (int) ($item['extra_price'] ?? 0)),
                ];
            }, $this->color_palette));
        }

        $palettes = config('car_colors', []);
        $brandName = $this->relationLoaded('brand') ? $this->brand?->name : ($this->brand_id ? $this->brand()->value('name') : 'Nissan');
        $fullName = trim(($brandName ?: 'Nissan')." {$this->name}");

        $swatches = $palettes[$fullName] ?? $palettes[$this->name] ?? null;

        if ($swatches !== null) {
            return array_values(array_map(function ($item) {
                return [
                    'name' => (string) ($item['name'] ?? ''),
                    'hex' => (string) ($item['hex'] ?? '#000000'),
                    'stock' => isset($item['stock']) ? (int) $item['stock'] : ($this->stock > 0 ? 1 : 0),
                    'extra_price' => max(0, (int) ($item['extra_price'] ?? 0)),
                ];
            }, $swatches));
        }

        $defaultColor = $this->color ?: 'Warna Standar';

        return [
            ['name' => $defaultColor, 'hex' => '#4A5568', 'stock' => max(0, (int) $this->stock), 'extra_price' => 0],
        ];
    }

    /**
     * Cek apakah warna tertentu memiliki stok unit tersedia.
     */
    public function isColorInStock(?string $colorName): bool
    {
        if (! $this->inStock()) {
            return false;
        }

        if ($colorName === null || $colorName === '') {
            return true;
        }

        foreach ($this->colorSwatches() as $swatch) {
            if (strcasecmp($swatch['name'], $colorName) === 0) {
                return ($swatch['stock'] ?? 0) > 0;
            }
        }

        return true;
    }

    /**
     * Dapatkan sisa stok untuk warna tertentu.
     */
    public function colorStock(?string $colorName): int
    {
        if (! $this->inStock()) {
            return 0;
        }

        if ($colorName === null || $colorName === '') {
            return (int) $this->stock;
        }

        foreach ($this->colorSwatches() as $swatch) {
            if (strcasecmp($swatch['name'], $colorName) === 0) {
                return (int) ($swatch['stock'] ?? 0);
            }
        }

        return (int) $this->stock;
    }

    /**
     * Kurangi stok untuk warna tertentu (dan perbarui total stok mobil).
     */
    public function decrementColorStock(?string $colorName): void
    {
        if (! empty($this->color_palette) && is_array($this->color_palette) && $colorName) {
            $palette = $this->color_palette;
            $found = false;

            foreach ($palette as &$item) {
                if (strcasecmp((string) ($item['name'] ?? ''), $colorName) === 0) {
                    $item['stock'] = max(0, ((int) ($item['stock'] ?? 1)) - 1);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if ($found) {
                $this->color_palette = $palette;
                $this->stock = max(0, array_sum(array_map(fn ($i) => (int) ($i['stock'] ?? 0), $palette)));
                $this->save();

                return;
            }
        }

        $this->decrement('stock');
    }

    /**
     * Tambahkan stok untuk warna tertentu (dan perbarui total stok mobil).
     */
    public function incrementColorStock(?string $colorName): void
    {
        if (! empty($this->color_palette) && is_array($this->color_palette) && $colorName) {
            $palette = $this->color_palette;
            $found = false;

            foreach ($palette as &$item) {
                if (strcasecmp((string) ($item['name'] ?? ''), $colorName) === 0) {
                    $item['stock'] = ((int) ($item['stock'] ?? 0)) + 1;
                    $found = true;
                    break;
                }
            }
            unset($item);

            if ($found) {
                $this->color_palette = $palette;
                $this->stock = array_sum(array_map(fn ($i) => (int) ($i['stock'] ?? 0), $palette));
                $this->save();

                return;
            }
        }

        $this->increment('stock');
    }

    /**
     * Dapatkan gambar yang cocok dengan nama varian warna, atau fallback ke primaryImage.
     */
    public function imageForColor(?string $colorName): ?CarImage
    {
        if ($colorName !== null && $colorName !== '') {
            // 1. Cari berdasarkan kecocokan nama warna eksplisit pada tabel car_images
            $matched = $this->images->first(function (CarImage $image) use ($colorName) {
                if (! $image->color_name) {
                    return false;
                }

                return strcasecmp($image->color_name, $colorName) === 0
                    || str_contains(mb_strtolower($image->color_name), mb_strtolower($colorName))
                    || str_contains(mb_strtolower($colorName), mb_strtolower($image->color_name));
            });

            if ($matched) {
                return $matched;
            }

            // 2. Cari berdasarkan indeks urutan di color_palette / colorSwatches
            $swatches = $this->colorSwatches();
            foreach ($swatches as $sIndex => $swatch) {
                $swatchName = (string) ($swatch['name'] ?? '');
                if ($swatchName !== '' && (
                    strcasecmp($swatchName, $colorName) === 0
                    || str_contains(mb_strtolower($swatchName), mb_strtolower($colorName))
                    || str_contains(mb_strtolower($colorName), mb_strtolower($swatchName))
                )) {
                    $imageAtIndex = $this->images->values()->get($sIndex);
                    if ($imageAtIndex) {
                        return $imageAtIndex;
                    }
                    break;
                }
            }
        }

        return $this->primaryImage ?? $this->images->first();
    }
}
