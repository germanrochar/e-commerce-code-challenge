<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\Paginated;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const array SORTABLE = ['name', 'sku', 'price_cents', 'stock', 'created_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $search = trim((string) $request->string('q'));
        $like = addcslashes($search, '\\%_');
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? (string) $request->query('sort') : 'created_at';
        $direction = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $products = Product::query()
            ->with('category:id,name,slug')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$like}%")->orWhere('sku', 'like', "{$like}%"),
            ))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/products/Index', [
            'products' => Paginated::of($products, ProductResource::class),
            'filters' => ['q' => $search, 'sort' => $sort, 'dir' => $direction],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('admin/products/Create', [
            'categories' => $this->categoryNames(),
        ]);
    }

    public function store(StoreProductRequest $request, SaveProduct $saveProduct): RedirectResponse
    {
        $product = $saveProduct->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->sku} created."]);

        return to_route('admin.products.index');
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        $product->load('category:id,name,slug');

        return Inertia::render('admin/products/Edit', [
            'product' => ProductResource::make($product)->resolve(),
            'categories' => $this->categoryNames(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, SaveProduct $saveProduct): RedirectResponse
    {
        $saveProduct->handle($request->validated(), $product);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->sku} updated."]);

        return to_route('admin.products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Product {$product->sku} deleted."]);

        return back();
    }

    private function categoryNames(): array
    {
        return Category::query()->orderBy('name')->pluck('name')->all();
    }
}
