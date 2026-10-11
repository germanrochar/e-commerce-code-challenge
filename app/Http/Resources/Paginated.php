<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

final class Paginated
{
    public static function of(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $items = array_map(
            fn (mixed $item): array => self::resource($resourceClass, $item)->resolve(),
            $paginator->items(),
        );

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'pages' => self::pageLinks($paginator),
            ],
        ];
    }

    private static function resource(string $resourceClass, mixed $item): JsonResource
    {
        $resource = new $resourceClass($item);

        if (! $resource instanceof JsonResource) {
            throw new InvalidArgumentException("[{$resourceClass}] is not a JSON resource.");
        }

        return $resource;
    }

    private static function pageLinks(LengthAwarePaginator $paginator): array
    {
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = array_unique(array_filter(
            [1, $current - 2, $current - 1, $current, $current + 1, $current + 2, $last],
            fn (int $page): bool => $page >= 1 && $page <= $last,
        ));
        sort($window);

        $links = [];
        $previous = 0;

        foreach ($window as $page) {
            if ($page - $previous > 1) {
                $links[] = ['label' => '…', 'url' => null, 'active' => false];
            }

            $links[] = ['label' => (string) $page, 'url' => $paginator->url($page), 'active' => $page === $current];
            $previous = $page;
        }

        return $links;
    }
}
