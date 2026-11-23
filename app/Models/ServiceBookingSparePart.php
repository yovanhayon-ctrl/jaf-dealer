<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBookingSparePart extends Model
{
    use HasFactory;

    public const MAX_NOTE_LENGTH = 255;

    protected $fillable = [
        'service_booking_id',
        'spare_part_id',
        'part_name',
        'part_number',
        'quantity',
        'price',
        'subtotal',
        'condition_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function serviceBooking(): BelongsTo
    {
        return $this->belongsTo(ServiceBooking::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function formattedPrice(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    public function formattedSubtotal(): string
    {
        return 'Rp '.number_format($this->subtotal, 0, ',', '.');
    }
}
