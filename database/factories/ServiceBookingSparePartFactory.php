<?php

namespace Database\Factories;

use App\Models\ServiceBooking;
use App\Models\ServiceBookingSparePart;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceBookingSparePart>
 */
class ServiceBookingSparePartFactory extends Factory
{
    protected $model = ServiceBookingSparePart::class;

    public function definition(): array
    {
        $part = SparePart::factory()->create();
        $qty = fake()->numberBetween(1, 3);
        $price = $part->price;

        return [
            'service_booking_id' => ServiceBooking::factory(),
            'spare_part_id' => $part->id,
            'part_name' => $part->name,
            'part_number' => $part->part_number,
            'quantity' => $qty,
            'price' => $price,
            'subtotal' => $price * $qty,
            'condition_note' => fake()->randomElement(['Aus parah', 'Pecah retak', 'Bocor', 'Kotor tersumbat']),
        ];
    }
}
