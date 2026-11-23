<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CarImageRequest;
use App\Models\Car;
use App\Models\CarImage;
use App\Support\CarImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CarImageController extends Controller
{
    public function index(Car $car): View
    {
        $car->load('images');

        return view('admin.cars.images.index', [
            'car' => $car,
            'max' => CarImage::MAX_PER_CAR,
        ]);
    }

    public function store(CarImageRequest $request, Car $car, CarImageProcessor $processor): RedirectResponse
    {
        $files = $request->file('images');
        $paths = [];

        try {
            DB::transaction(function () use ($car, $files, $processor, &$paths) {
                // Kunci baris mobil agar dua upload bersamaan tidak melewati batas atau membuat dua gambar utama.
                Car::whereKey($car->id)->lockForUpdate()->first();

                $images = CarImage::where('car_id', $car->id);

                if ($images->clone()->count() + count($files) > CarImage::MAX_PER_CAR) {
                    throw ValidationException::withMessages([
                        'images' => 'Maksimal '.CarImage::MAX_PER_CAR.' gambar per mobil.',
                    ]);
                }

                $needsPrimary = ! $images->clone()->where('is_primary', true)->exists();
                $sortOrder = (int) $images->clone()->max('sort_order');

                foreach ($files as $file) {
                    // Dikecilkan & disimpan sebagai WebP + thumbnail; nama file acak, bukan nama asli dari pengguna.
                    try {
                        $stored = $processor->store($file->getRealPath(), CarImage::directory($car->id), CarImage::DISK);
                    } catch (RuntimeException) {
                        throw ValidationException::withMessages([
                            'images' => "Gambar \"{$file->getClientOriginalName()}\" tidak bisa diproses. Coba simpan ulang sebagai JPG lalu unggah lagi.",
                        ]);
                    }
                    array_push($paths, $stored['path'], $stored['thumb_path']);

                    CarImage::create([
                        'car_id' => $car->id,
                        'path' => $stored['path'],
                        'thumb_path' => $stored['thumb_path'],
                        'is_primary' => $needsPrimary,
                        'sort_order' => ++$sortOrder,
                    ]);

                    $needsPrimary = false;
                }
            });
        } catch (Throwable $e) {
            // Data gagal disimpan: jangan tinggalkan file yatim di disk.
            Storage::disk(CarImage::DISK)->delete($paths);

            throw $e;
        }

        return $this->backToGallery($car)
            ->with('success', count($files).' gambar berhasil diunggah.');
    }

    public function makePrimary(Car $car, CarImage $image): RedirectResponse
    {
        DB::transaction(function () use ($car, $image) {
            CarImage::where('car_id', $car->id)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return $this->backToGallery($car)->with('success', 'Gambar utama berhasil diubah.');
    }

    public function updateColor(Request $request, Car $car, CarImage $image): RedirectResponse
    {
        $colorValue = (string) $request->input('color');

        if ($colorValue !== '' && str_contains($colorValue, '|')) {
            [$name, $hex] = explode('|', $colorValue, 2);
        } else {
            $name = $request->input('color_name');
            $hex = $request->input('color_hex');
        }

        $name = $name ? trim((string) $name) : null;
        $hex = $hex ? trim((string) $hex) : null;

        $image->update([
            'color_name' => $name,
            'color_hex' => $hex,
        ]);

        return $this->backToGallery($car)->with('success', 'Warna gambar berhasil disimpan.');
    }

    public function storeColorPalette(Request $request, Car $car): RedirectResponse
    {
        if ($request->has('extra_price') && is_string($request->input('extra_price'))) {
            $cleaned = preg_replace('/\D/', '', $request->input('extra_price'));
            $request->merge(['extra_price' => $cleaned !== '' ? (int) $cleaned : null]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'hex' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'extra_price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'name.required' => 'Nama warna wajib diisi.',
            'hex.required' => 'Kode warna (HEX) wajib diisi.',
            'hex.regex' => 'Format kode warna harus diawali # (contoh: #EAB308).',
            'stock.integer' => 'Stok harus berupa bilangan bulat.',
            'stock.min' => 'Stok minimal 0.',
            'extra_price.integer' => 'Biaya tambahan cat harus berupa angka.',
            'extra_price.min' => 'Biaya tambahan cat minimal Rp 0.',
        ]);

        $swatches = $car->colorSwatches();
        $name = trim($validated['name']);
        $hex = strtoupper(trim($validated['hex']));
        $stock = isset($validated['stock']) ? max(0, (int) $validated['stock']) : 1;
        $extraPrice = isset($validated['extra_price']) ? max(0, (int) $validated['extra_price']) : 0;

        $swatches[] = [
            'name' => $name,
            'hex' => $hex,
            'stock' => $stock,
            'extra_price' => $extraPrice,
        ];

        $totalStock = array_sum(array_map(fn ($s) => (int) ($s['stock'] ?? 0), $swatches));
        $car->update([
            'color_palette' => array_values($swatches),
            'stock' => $totalStock,
        ]);

        return $this->backToGallery($car)->with('success', "Warna {$name} ({$hex}) dengan stok {$stock} unit berhasil ditambahkan ke mobil.");
    }

    public function updateColorPaletteStock(Request $request, Car $car): RedirectResponse
    {
        $stocks = (array) $request->input('stocks', []);
        $extraPrices = (array) $request->input('extra_prices', []);
        $swatches = array_values($car->colorSwatches());

        foreach ($swatches as $idx => &$swatch) {
            if (array_key_exists($idx, $stocks)) {
                $val = $stocks[$idx];
                $swatch['stock'] = ($val !== null && $val !== '') ? max(0, (int) $val) : 0;
            }
            if (array_key_exists($idx, $extraPrices)) {
                $val = $extraPrices[$idx];
                if (is_string($val)) {
                    $val = preg_replace('/\D/', '', $val);
                }
                $swatch['extra_price'] = ($val !== null && $val !== '') ? max(0, (int) $val) : 0;
            }
        }
        unset($swatch);

        $totalStock = array_sum(array_map(fn ($s) => (int) ($s['stock'] ?? 0), $swatches));
        $car->update([
            'color_palette' => $swatches,
            'stock' => $totalStock,
        ]);

        return $this->backToGallery($car)->with('success', "Stok & harga varian warna berhasil diperbarui (Total stok: {$totalStock} unit).");
    }

    public function destroyColorPalette(Car $car, int $index): RedirectResponse
    {
        $swatches = array_values($car->colorSwatches());

        if (isset($swatches[$index])) {
            $deletedName = $swatches[$index]['name'];
            unset($swatches[$index]);
            $swatches = array_values($swatches);
            $totalStock = array_sum(array_map(fn ($s) => (int) ($s['stock'] ?? 0), $swatches));

            $car->update([
                'color_palette' => $swatches,
                'stock' => $totalStock,
            ]);

            return $this->backToGallery($car)->with('success', "Warna {$deletedName} berhasil dihapus dari mobil.");
        }

        return $this->backToGallery($car)->with('warning', 'Warna tidak ditemukan.');
    }

    public function move(Car $car, CarImage $image, string $direction): RedirectResponse
    {
        $ids = $this->orderedImageIds($car);
        $position = $ids->search($image->id);
        $target = $direction === 'naik' ? $position - 1 : $position + 1;

        if ($target < 0 || $target >= $ids->count()) {
            $edge = $direction === 'naik' ? 'pertama' : 'terakhir';

            return $this->backToGallery($car)->with('warning', "Gambar sudah berada di urutan {$edge}.");
        }

        $ids = $ids->all();
        [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];

        // Tulis ulang urutan 1..n sekaligus merapikan nilai sort_order yang ganda.
        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                CarImage::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        return $this->backToGallery($car)->with('success', 'Urutan gambar berhasil diubah.');
    }

    public function destroy(Car $car, CarImage $image): RedirectResponse
    {
        $colorName = $image->color_name;
        $colorRemoved = false;

        DB::transaction(function () use ($car, $image, $colorName, &$colorRemoved) {
            $image->delete();

            $nextId = $this->orderedImageIds($car)->first();

            if ($image->is_primary && $nextId !== null) {
                CarImage::whereKey($nextId)->update(['is_primary' => true]);
            }

            // Jika gambar terhubung ke suatu warna dan tidak ada gambar lain yang memakai warna tersebut,
            // otomatis hapus warna tersebut dari daftar color_palette mobil dan sinkronkan total stok.
            if ($colorName && ! empty($car->color_palette) && is_array($car->color_palette)) {
                $stillUsed = CarImage::where('car_id', $car->id)
                    ->where('color_name', $colorName)
                    ->exists();

                if (! $stillUsed) {
                    $palette = array_values(array_filter(
                        $car->color_palette,
                        fn ($item) => strcasecmp((string) ($item['name'] ?? ''), $colorName) !== 0
                    ));

                    $totalStock = max(0, array_sum(array_map(fn ($i) => (int) ($i['stock'] ?? 0), $palette)));
                    $car->update([
                        'color_palette' => $palette,
                        'stock' => $totalStock,
                    ]);
                    $colorRemoved = true;
                }
            }
        });

        // File dihapus hanya setelah data berhasil dihapus.
        Storage::disk(CarImage::DISK)->delete($image->files());

        $message = $image->is_primary && $car->images()->exists()
            ? 'Gambar berhasil dihapus. Gambar berikutnya dijadikan gambar utama.'
            : 'Gambar berhasil dihapus.';

        if ($colorRemoved) {
            $message .= " Pilihan warna '{$colorName}' otomatis dihapus dari daftar varian warna.";
        }

        return $this->backToGallery($car)->with('success', $message);
    }

    /**
     * @return Collection<int, int>
     */
    private function orderedImageIds(Car $car): Collection
    {
        return CarImage::where('car_id', $car->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');
    }

    private function backToGallery(Car $car): RedirectResponse
    {
        return redirect()->route('admin.cars.images.index', $car);
    }
}
