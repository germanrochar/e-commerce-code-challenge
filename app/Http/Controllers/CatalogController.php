<?php

namespace App\Http\Controllers;

use App\Catalog\ProductSearch;
use App\Http\Resources\Paginated;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = ProductSearch::fromRequest($request);
        $category = $search->category();

        return Inertia::render('store/Catalog', [
            'products' => Paginated::of($search->paginate(), ProductResource::class),
            'categories' => $search->categoryFacets(),
            'filters' => $search->filters(),
            'activeCategory' => $category ? ['name' => $category->name, 'slug' => $category->slug] : null,
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load('category:id,name,slug');

        $related = Product::query()
            ->with('category:id,name,slug')
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->orderByDesc('stock')
            ->orderBy('name')
            ->limit(4)
            ->get();

        return Inertia::render('store/Product', [
            'product' => ProductResource::make($product)->resolve(),
            'related' => ProductResource::collection($related)->resolve(),
        ]);
    }
}
