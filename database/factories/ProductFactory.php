<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('??-#####')),
            'name' => ucfirst(fake()->word().' '.fake()->word().' '.fake()->word()),
            'description' => fake()->sentence(12),
            'price_cents' => fake()->numberBetween(199, 49999),
            'stock' => fake()->numberBetween(20, 500),
            'weight_kg' => fake()->randomFloat(3, 0.05, 15),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function withStock(int $stock): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function priced(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }
}
