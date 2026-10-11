<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = Category::normalizeName(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => [
            'name' => Category::normalizeName($name),
            'slug' => Category::slugFor($name),
        ]);
    }
}
