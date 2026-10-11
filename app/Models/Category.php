<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['name', 'slug'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public static function normalizeName(string $name): string
    {
        return Str::of($name)->squish()->ucfirst()->toString();
    }

    public static function slugFor(string $name): string
    {
        return Str::slug(self::normalizeName($name));
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = self::normalizeName($name);

        return self::query()->firstOrCreate(['slug' => self::slugFor($name)], ['name' => $name]);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
