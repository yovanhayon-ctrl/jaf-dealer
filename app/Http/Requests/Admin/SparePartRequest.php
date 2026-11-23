<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesDigits;
use App\Models\SparePart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SparePartRequest extends FormRequest
{
    use NormalizesDigits;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $imageUrl = trim((string) $this->input('image_url'));

        $this->merge([
            'part_number' => strtoupper(trim((string) $this->input('part_number'))),
            'name' => Str::squish((string) $this->input('name')),
            'price' => $this->digitsOnly('price'),
            'stock' => $this->digitsOnly('stock'),
            'min_stock' => $this->digitsOnly('min_stock') ?: 5,
            'is_active' => $this->boolean('is_active', true),
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sparePart = $this->route('sparePart') ?? $this->route('spare_part');
        $sparePartId = $sparePart instanceof SparePart ? $sparePart->id : $sparePart;

        return [
            'part_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('spare_parts', 'part_number')->ignore($sparePartId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', Rule::in(array_keys(SparePart::CATEGORIES))],
            'compatibility' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'string', Rule::in(array_keys(SparePart::UNITS))],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)],
            'image_url' => ['nullable', 'string', 'url', 'max:2048'],
            'save_to_storage' => ['nullable', 'boolean'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'part_number' => 'kode spare part / SKU',
            'name' => 'nama spare part',
            'category' => 'kategori',
            'compatibility' => 'kecocokan model mobil',
            'price' => 'harga',
            'stock' => 'stok',
            'min_stock' => 'batas minimum stok',
            'unit' => 'satuan',
            'description' => 'deskripsi',
            'image' => 'file foto produk',
            'image_url' => 'link URL foto produk',
            'is_active' => 'status aktif',
        ];
    }
}
