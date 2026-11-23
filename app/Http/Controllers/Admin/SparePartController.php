<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SparePartRequest;
use App\Models\ActivityLog;
use App\Models\SparePart;
use App\Support\CarImageProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SparePartController extends Controller
{
    public const SORTS = [
        'terbaru' => ['created_at', 'desc'],
        'nama_asc' => ['name', 'asc'],
        'harga_termurah' => ['price', 'asc'],
        'harga_termahal' => ['price', 'desc'],
        'stok_tersedikit' => ['stock', 'asc'],
        'stok_terbanyak' => ['stock', 'desc'],
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $category = array_key_exists((string) $request->query('kategori'), SparePart::CATEGORIES) ? $request->query('kategori') : null;
        $stockStatus = in_array((string) $request->query('stok'), ['tersedia', 'menipis', 'habis'], true) ? $request->query('stok') : null;
        $activeStatus = in_array((string) $request->query('status'), ['aktif', 'nonaktif'], true) ? $request->query('status') : null;
        $sortKey = array_key_exists((string) $request->query('urut'), self::SORTS) ? $request->query('urut') : 'terbaru';
        [$sortColumn, $sortDirection] = self::SORTS[$sortKey];

        $query = SparePart::query()
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('part_number', 'like', "%{$search}%")
                        ->orWhere('compatibility', 'like', "%{$search}%");
                });
            })
            ->when($category !== null, fn (Builder $q) => $q->where('category', $category))
            ->when($stockStatus === 'tersedia', fn (Builder $q) => $q->where('stock', '>', 0))
            ->when($stockStatus === 'menipis', fn (Builder $q) => $q->lowStock())
            ->when($stockStatus === 'habis', fn (Builder $q) => $q->outOfStock())
            ->when($activeStatus === 'aktif', fn (Builder $q) => $q->where('is_active', true))
            ->when($activeStatus === 'nonaktif', fn (Builder $q) => $q->where('is_active', false));

        $stats = [
            'total_items' => SparePart::count(),
            'in_stock_count' => SparePart::where('stock', '>', 0)->count(),
            'low_stock_count' => SparePart::lowStock()->count(),
            'total_inventory_value' => (int) SparePart::sum(DB::raw('price * stock')),
        ];

        $spareParts = (clone $query)
            ->orderBy($sortColumn, $sortDirection)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.spare-parts.index', [
            'spareParts' => $spareParts,
            'stats' => $stats,
            'filters' => [
                'q' => $search,
                'kategori' => $category,
                'stok' => $stockStatus,
                'status' => $activeStatus,
                'urut' => $sortKey,
            ],
            'hasFilters' => $search !== '' || $category !== null || $stockStatus !== null || $activeStatus !== null,
        ]);
    }

    public function create(): View
    {
        return view('admin.spare-parts.create', [
            'sparePart' => new SparePart([
                'stock' => 0,
                'min_stock' => 5,
                'unit' => 'pcs',
                'is_active' => true,
            ]),
        ]);
    }

    public function store(SparePartRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $imagePath = $this->handleImageUpload($request);

        unset($data['image_url'], $data['remove_image'], $data['save_to_storage']);

        $sparePart = SparePart::create([
            ...$data,
            'slug' => SparePart::uniqueSlug($data['name']),
            'image' => $imagePath,
        ]);

        ActivityLog::record(
            ActivityLog::CATEGORY_ACCOUNT,
            'spare_part.created',
            "Menambah spare part {$sparePart->name} (SKU: {$sparePart->part_number})",
            $sparePart
        );

        return redirect()->route('admin.spare-parts.index')
            ->with('success', "Spare part \"{$sparePart->name}\" berhasil ditambahkan.");
    }

    public function edit(SparePart $sparePart): View
    {
        return view('admin.spare-parts.edit', [
            'sparePart' => $sparePart,
        ]);
    }

    public function update(SparePartRequest $request, SparePart $sparePart): RedirectResponse
    {
        $data = $request->validated();
        $imagePath = $this->handleImageUpload($request, $sparePart->image);

        unset($data['image_url'], $data['remove_image'], $data['save_to_storage']);

        $sparePart->update([
            ...$data,
            'slug' => SparePart::uniqueSlug($data['name'], $sparePart->id),
            'image' => $imagePath,
        ]);

        ActivityLog::record(
            ActivityLog::CATEGORY_ACCOUNT,
            'spare_part.updated',
            "Memperbarui spare part {$sparePart->name} (SKU: {$sparePart->part_number})",
            $sparePart
        );

        return redirect()->route('admin.spare-parts.index')
            ->with('success', "Spare part \"{$sparePart->name}\" berhasil diperbarui.");
    }

    public function toggleActive(SparePart $sparePart): RedirectResponse
    {
        $sparePart->update(['is_active' => ! $sparePart->is_active]);

        $status = $sparePart->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back(fallback: route('admin.spare-parts.index'))
            ->with('success', "Spare part \"{$sparePart->name}\" {$status}.");
    }

    public function destroy(SparePart $sparePart): RedirectResponse
    {
        if ($sparePart->image && ! $sparePart->isExternalImage()) {
            Storage::disk('public')->delete($sparePart->image);
        }

        $name = $sparePart->name;
        $sparePart->delete();

        ActivityLog::record(ActivityLog::CATEGORY_DELETE, 'spare_part.deleted', "Menghapus spare part \"{$name}\"");

        return redirect()->route('admin.spare-parts.index')
            ->with('success', "Spare part \"{$name}\" berhasil dihapus.");
    }

    private function handleImageUpload(SparePartRequest $request, ?string $currentImage = null): ?string
    {
        $isCurrentExternal = $currentImage && (str_starts_with($currentImage, 'http://') || str_starts_with($currentImage, 'https://'));

        if ($request->boolean('remove_image')) {
            if ($currentImage && ! $isCurrentExternal) {
                Storage::disk('public')->delete($currentImage);
            }

            return null;
        }

        if ($request->hasFile('image')) {
            if ($currentImage && ! $isCurrentExternal) {
                Storage::disk('public')->delete($currentImage);
            }

            try {
                return (new CarImageProcessor)->storeSingle($request->file('image')->getRealPath(), 'spare-parts', 800, 800);
            } catch (\Throwable) {
                return $request->file('image')->store('spare-parts', 'public');
            }
        }

        if ($request->filled('image_url')) {
            return $this->syncImageFromUrl($request, $currentImage, $isCurrentExternal);
        }

        if ($isCurrentExternal && $request->has('image_url') && ! $request->filled('image_url')) {
            return null;
        }

        return $currentImage;
    }

    /**
     * Memproses dan menyimpan gambar dari URL eksternal dengan proteksi anti-SSRF.
     *
     * @throws ValidationException jika URL mengarah ke jaringan privat/lokal
     */
    protected function syncImageFromUrl(SparePartRequest $request, ?string $currentImage = null, bool $isCurrentExternal = false): ?string
    {
        $newUrl = trim((string) $request->input('image_url'));

        if (! $this->isSafePublicUrl($newUrl)) {
            throw ValidationException::withMessages([
                'image_url' => 'URL gambar tidak valid atau mengarah ke alamat jaringan privat/internal yang tidak diizinkan.',
            ]);
        }

        // Unduh & simpan salinan ke storage lokal jika opsi aktif
        if ($request->boolean('save_to_storage', true)) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'image/*,*/*;q=0.8',
                ])->timeout(8)->get($newUrl);

                $contentType = (string) $response->header('Content-Type');
                if ($response->successful() && (str_starts_with($contentType, 'image/') || str_contains($contentType, 'octet-stream'))) {
                    $tempPath = tempnam(sys_get_temp_dir(), 'sp_img_');
                    file_put_contents($tempPath, $response->body());

                    try {
                        $stored = (new CarImageProcessor)->storeSingle($tempPath, 'spare-parts', 800, 800);
                        @unlink($tempPath);

                        if ($currentImage && $currentImage !== $stored && ! $isCurrentExternal) {
                            Storage::disk('public')->delete($currentImage);
                        }

                        return $stored;
                    } catch (\Throwable) {
                        @unlink($tempPath);
                    }
                }
            } catch (\Throwable) {
                // Fallback ke URL eksternal jika gagal mengunduh
            }
        }

        if ($currentImage && $currentImage !== $newUrl && ! $isCurrentExternal) {
            Storage::disk('public')->delete($currentImage);
        }

        return $newUrl;
    }

    /**
     * Memvalidasi bahwa URL aman dari celah Server-Side Request Forgery (SSRF):
     * - Hanya mengizinkan skema http atau https
     * - Memblokir IP privat, loopback, dan reserved range (FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
     * - Memblokir hostname internal/localhost
     */
    public function isSafePublicUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parsed['host']);

        // Blokir localhost dan domain internal
        if (in_array($host, ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.lan')) {
            return false;
        }

        // Jika host adalah format IP langsung
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // Resolusi hostname untuk mengecek IP di baliknya (mencegah DNS rebinding & nip.io)
        $resolvedIp = @gethostbyname($host);
        if ($resolvedIp && $resolvedIp !== $host) {
            return (bool) filter_var(
                $resolvedIp,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // Jika hostname tidak bisa di-resolve ke IP (misal offline/testing mock)
        return ! filter_var($host, FILTER_VALIDATE_IP);
    }
}
