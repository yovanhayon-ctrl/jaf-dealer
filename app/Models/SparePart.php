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
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * Link WhatsApp pemesanan suku cadang dengan format pesan otomatis.
     */
    public function whatsappOrderUrl(): ?string
    {
        $number = preg_replace('/\D/', '', (string) config('dealer.whatsapp'));

        if ($number === '') {
            return null;
        }

        $dealerName = config('dealer.name') ?: 'JAF Dealer';
        $message = "Halo {$dealerName}, saya ingin memesan suku cadang:\n"
            ."• Part: {$this->name}\n"
            ."• Kode Part: {$this->part_number}\n"
            ."• Harga: {$this->formattedPrice()} / {$this->unit}\n\n"
            .'Apakah stok masih tersedia? Link: '.route('spare-parts.show', $this);

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }
}
