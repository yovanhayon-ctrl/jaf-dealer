<?php

namespace Database\Factories;

use App\Models\SparePart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SparePart>
 */
class SparePartFactory extends Factory
{
    protected $model = SparePart::class;

    public function definition(): array
    {
        $name = fake()->words(3, true).' '.fake()->numberBetween(10, 99);
        $categories = array_keys(SparePart::CATEGORIES);

        return [
            'part_number' => strtoupper(fake()->bothify('??-####-??')),
            'name' => ucwords($name),
            'slug' => SparePart::uniqueSlug($name),
            'category' => fake()->randomElement($categories),
            'compatibility' => 'Nissan Grand Livina, Serena, March',
            'price' => fake()->numberBetween(5, 150) * 10_000,
            'stock' => fake()->numberBetween(5, 50),
            'min_stock' => 5,
            'unit' => 'pcs',
            'description' => fake()->sentence(),
            'image' => null,
            'is_active' => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock' => 2,
            'min_stock' => 5,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
