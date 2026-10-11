<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { SearchX, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import Pagination from '@/components/Pagination.vue';
import CategoryFacetList from '@/components/store/CategoryFacetList.vue';
import CategoryPills from '@/components/store/CategoryPills.vue';
import ProductCard from '@/components/store/ProductCard.vue';
import ProductGridSkeleton from '@/components/store/ProductGridSkeleton.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { sortOptions } from '@/lib/catalog';
import { home } from '@/routes';
import type {
    CatalogFilters,
    CatalogSort,
    CategoryFacet,
    CategorySummary,
    Paginated,
    Product,
} from '@/types';

const props = defineProps<{
    products: Paginated<Product>;
    categories: CategoryFacet[];
    filters: CatalogFilters;
    activeCategory: CategorySummary | null;
}>();

const loading = ref(false);
const filtersOpen = ref(false);

const totalMatches = computed(() =>
    props.categories.reduce((sum, category) => sum + category.count, 0),
);

const visibleSortOptions = computed(() =>
    sortOptions.filter(
        (option) => props.filters.q !== '' || option.value !== 'relevance:asc',
    ),
);

const sortValue = computed(() => `${props.filters.sort}:${props.filters.dir}`);

const heading = computed(() => {
    if (props.filters.q) {
        return `Results for “${props.filters.q}”`;
    }

    return props.activeCategory?.name ?? 'All products';
});

const visit = (changes: Partial<CatalogFilters>) => {
    const next = { ...props.filters, ...changes };
    const params: Record<string, string> = {};

    if (next.q) {
        params.q = next.q;
    }

    if (next.category) {
        params.category = next.category;
    }

    const defaultSort: CatalogSort = next.q ? 'relevance' : 'name';

    if (next.sort !== defaultSort || next.dir !== 'asc') {
        params.sort = next.sort;
        params.dir = next.dir;
    }

    router.get(home.url(), params, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const selectCategory = (slug: string | null) => {
    filtersOpen.value = false;
    visit({ category: slug });
};

const changeSort = (value: unknown) => {
    const [sort, dir] = String(value).split(':') as [
        CatalogSort,
        'asc' | 'desc',
    ];
    visit({ sort, dir });
};

const clearSearch = () =>
    visit({
        q: '',
        sort: props.filters.sort === 'relevance' ? 'name' : props.filters.sort,
    });

const clearAll = () =>
    visit({ q: '', category: null, sort: 'name', dir: 'asc' });
</script>

<template>
    <Head :title="heading" />

    <div class="flex flex-col gap-6">
        <section
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 px-6 py-10 text-white sm:px-10"
        >
            <p
                class="text-sm font-medium tracking-widest text-white/60 uppercase"
            >
                Storefront
            </p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                {{ heading }}
            </h1>
            <p class="mt-2 text-white/70">
                {{ products.meta.total.toLocaleString() }}
                {{ products.meta.total === 1 ? 'product' : 'products' }}
                <template v-if="activeCategory && filters.q">
                    in {{ activeCategory.name }}</template
                >
            </p>
        </section>

        <CategoryPills
            :categories="categories"
            :active="filters.category"
            :total="totalMatches"
            @select="selectCategory"
        />

        <div class="grid gap-8 lg:grid-cols-[15rem_1fr]">
            <aside class="hidden lg:block">
                <div class="sticky top-24">
                    <h2
                        class="mb-3 px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Categories
                    </h2>
                    <CategoryFacetList
                        :categories="categories"
                        :active="filters.category"
                        :total="totalMatches"
                        @select="selectCategory"
                    />
                </div>
            </aside>

            <div class="flex min-w-0 flex-col gap-5">
                <div class="flex flex-wrap items-center gap-3">
                    <Sheet v-model:open="filtersOpen">
                        <SheetTrigger as-child>
                            <Button variant="outline" class="lg:hidden">
                                <SlidersHorizontal class="size-4" />
                                Filters
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-80 overflow-y-auto">
                            <SheetHeader>
                                <SheetTitle>Categories</SheetTitle>
                                <SheetDescription
                                    >Narrow down the catalog.</SheetDescription
                                >
                            </SheetHeader>
                            <div class="px-2 pb-6">
                                <CategoryFacetList
                                    :categories="categories"
                                    :active="filters.category"
                                    :total="totalMatches"
                                    @select="selectCategory"
                                />
                            </div>
                        </SheetContent>
                    </Sheet>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="filters.q"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-3 py-1 text-sm hover:bg-muted/70"
                            @click="clearSearch"
                        >
                            “{{ filters.q }}”
                            <X class="size-3.5" />
                            <span class="sr-only">Clear search</span>
                        </button>
                        <button
                            v-if="activeCategory"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-muted px-3 py-1 text-sm hover:bg-muted/70"
                            @click="selectCategory(null)"
                        >
                            {{ activeCategory.name }}
                            <X class="size-3.5" />
                            <span class="sr-only">Remove category filter</span>
                        </button>
                        <button
                            v-if="filters.q && activeCategory"
                            type="button"
                            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                            @click="clearAll"
                        >
                            Clear all
                        </button>
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        <span
                            class="hidden text-sm text-muted-foreground sm:inline"
                            >Sort by</span
                        >
                        <Select
                            :model-value="sortValue"
                            @update:model-value="changeSort"
                        >
                            <SelectTrigger
                                class="w-48"
                                aria-label="Sort products"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in visibleSortOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-4 sm:gap-6 md:grid-cols-3"
                    :aria-busy="loading"
                >
                    <ProductGridSkeleton v-if="loading" :count="6" />
                    <template v-else>
                        <ProductCard
                            v-for="product in products.data"
                            :key="product.id"
                            :product="product"
                        />
                    </template>
                </div>

                <div
                    v-if="!loading && products.data.length === 0"
                    class="flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-16 text-center"
                >
                    <div class="rounded-full bg-muted p-4">
                        <SearchX class="size-6 text-muted-foreground" />
                    </div>
                    <h2 class="text-lg font-semibold">No products found</h2>
                    <p class="max-w-sm text-sm text-muted-foreground">
                        Try a different search term or browse all categories.
                    </p>
                    <Button variant="outline" @click="clearAll"
                        >Show all products</Button
                    >
                </div>

                <Pagination
                    v-if="products.data.length > 0"
                    :meta="products.meta"
                    :links="products.links"
                    item-label="products"
                />
            </div>
        </div>
    </div>
</template>
