<?php

namespace App\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class ProductSearch
{
    public const int PER_PAGE = 12;

    public const int MAX_TERM_LENGTH = 100;

    private const array SORTS = ['relevance', 'name', 'price'];

    private const array STOPWORDS = [
        'a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is',
        'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will',
        'with', 'und', 'www',
    ];

    private ?Category $category = null;

    private bool $categoryResolved = false;

    public function __construct(
        public readonly string $term = '',
        public readonly ?string $categorySlug = null,
        public readonly string $sort = 'name',
        public readonly string $direction = 'asc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $term = $request->query('q');
        $term = Str::of(is_string($term) ? $term : '')->squish()->limit(self::MAX_TERM_LENGTH, '')->toString();
        $category = $request->query('category');
        $sort = $request->query('sort');
        $direction = $request->query('dir');

        $sort = is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : ($term !== '' ? 'relevance' : 'name');

        if ($sort === 'relevance' && $term === '') {
            $sort = 'name';
        }

        return new self(
            term: $term,
            categorySlug: is_string($category) && preg_match('/^[a-z0-9-]{1,120}$/', $category) === 1 ? $category : null,
            sort: $sort,
            direction: $direction === 'desc' ? 'desc' : 'asc',
        );
    }

    public function paginate(): LengthAwarePaginator
    {
        $query = $this->matching()->with('category:id,name,slug');

        $category = $this->category();

        if ($this->categorySlug !== null) {
            $query->where('category_id', $category?->getKey() ?? 0);
        }

        $this->applySort($query);

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    public function categoryFacets(): array
    {
        $counts = $this->matching()
            ->toBase()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => (int) ($counts[$category->id] ?? 0),
            ])
            ->filter(fn (array $facet) => $facet['count'] > 0 || $facet['slug'] === $this->categorySlug)
            ->values()
            ->all();
    }

    public function category(): ?Category
    {
        if (! $this->categoryResolved) {
            $this->category = $this->categorySlug === null
                ? null
                : Category::query()->where('slug', $this->categorySlug)->first();
            $this->categoryResolved = true;
        }

        return $this->category;
    }

    public function filters(): array
    {
        return [
            'q' => $this->term,
            'category' => $this->categorySlug,
            'sort' => $this->sort,
            'dir' => $this->direction,
        ];
    }

    private function matching(): Builder
    {
        $query = Product::query();

        if ($this->term === '') {
            return $query;
        }

        $booleanQuery = $this->booleanQuery();
        $like = '%'.addcslashes($this->term, '\\%_').'%';

        return $query->where(function (Builder $query) use ($booleanQuery, $like): void {
            if ($booleanQuery !== null) {
                $query->whereFullText(['name', 'description'], $booleanQuery, ['mode' => 'boolean']);
            } else {
                $query->where('name', 'like', $like);
            }

            $query->orWhere('sku', strtoupper($this->term));
        });
    }

    private function applySort(Builder $query): void
    {
        $booleanQuery = $this->booleanQuery();
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        match (true) {
            $this->sort === 'relevance' && $booleanQuery !== null => $query
                ->orderByRaw('MATCH(name, description) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanQuery])
                ->orderBy('name'),
            $this->sort === 'price' => $query->orderBy('price_cents', $direction),
            default => $query->orderBy('name', $this->sort === 'name' ? $direction : 'asc'),
        };

        $query->orderBy('id');
    }

    private function booleanQuery(): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($this->term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_filter(
            array_unique($words),
            fn (string $word) => mb_strlen($word) >= 2 && ! in_array($word, self::STOPWORDS, true),
        );

        if ($words === []) {
            return null;
        }

        return implode(' ', array_map(fn (string $word) => "+{$word}*", $words));
    }
}
