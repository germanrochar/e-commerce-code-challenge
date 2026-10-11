<?php

namespace App\Http\Controllers;

use App\Catalog\CatalogCache;
use App\Catalog\ProductSearch;
use App\Http\Resources\Paginated;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request, CatalogCache $cache): Response
    {
        $search = ProductSearch::fromRequest($request);
        $page = max(1, $request->integer('page', 1));

        $payload = $cache->withLiveStock($cache->rememberPage($search, $page, function () use ($search): array {
            $category = $search->category();

            return [
                'products' => Paginated::of($search->paginate(), ProductResource::class),
                'categories' => $search->categoryFacets(),
                'activeCategory' => $category ? ['name' => $category->name, 'slug' => $category->slug] : null,
            ];
        }));

        return Inertia::render('store/Catalog', [
            ...$payload,
            'filters' => $search->filters(),
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
