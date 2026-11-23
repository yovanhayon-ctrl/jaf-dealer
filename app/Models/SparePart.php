<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SparePart extends Model
{
    use HasFactory, HasUniqueSlug;

    public const CATEGORIES = [
        'oli' => 'Oli & Cairan',
        'mesin' => 'Komponen Mesin',
        'pengereman' => 'Pengereman',
        'kelistrikan' => 'Kelistrikan & Aki',
        'suspensi' => 'Suspensi & Kemudi',
        'bodi' => 'Bodi & Eksterior',
        'aksesori' => 'Aksesori & Interior',
        'lainnya' => 'Lain-lain',
    ];

    public const UNITS = [
        'pcs' => 'Pcs / Buah',
        'liter' => 'Liter',
        'botol' => 'Botol',
        'set' => 'Set',
        'box' => 'Box',
        'meter' => 'Meter',
    ];

    protected $fillable = [
        'part_number',
        'name',
        'slug',
        'category',
        'compatibility',
        'price',
        'stock',
        'min_stock',
        'unit',
        'description',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= $this->min_stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock <= 0;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function formattedPrice(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    public function isExternalImage(): bool
    {
        return (bool) ($this->image && (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')));
    }

    /**
     * Link WhatsApp pemesanan suku cadang dengan format pesan otomatis.
     */
    public function whatsappOrderUrl(?int $qty = 1): ?string
    {
        $number = preg_replace('/\D/', '', (string) config('dealer.whatsapp'));

        if ($number === '') {
            return null;
        }

        $qty = max(1, $qty ?? 1);
        $totalPrice = $this->price * $qty;
        $totalFormatted = 'Rp '.number_format($totalPrice, 0, ',', '.');
        $unitPriceFormatted = $this->formattedPrice();

        $dealerName = config('dealer.name') ?: 'JAF Dealer';
        $message = "Halo {$dealerName}, saya ingin memesan suku cadang:\n"
            ."• Part: {$this->name}\n"
            ."• Kode SKU: {$this->part_number}\n"
            ."• Jumlah: {$qty} {$this->unit}\n"
            ."• Harga Satuan: {$unitPriceFormatted}\n"
            ."• Estimasi Total: {$totalFormatted}\n\n"
            .'Apakah stok masih tersedia? Link: '.route('spare-parts.show', $this);

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }
}
