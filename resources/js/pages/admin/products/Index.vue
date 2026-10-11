<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import admin from '@/routes/admin';
import type { Paginated, Product } from '@/types';

type SortKey = 'name' | 'sku' | 'price_cents' | 'stock' | 'created_at';

const props = defineProps<{
    products: Paginated<Product>;
    filters: { q: string; sort: SortKey; dir: 'asc' | 'desc' };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: admin.products.index() }],
    },
});

const search = ref(props.filters.q);
const productToDelete = ref<Product | null>(null);

const visit = (params: Record<string, string>) => {
    router.get(
        admin.products.index.url(),
        { ...props.filters, ...params },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const debouncedSearch = useDebounceFn(
    (value: string) => visit({ q: value }),
    300,
);

watch(search, (value) => debouncedSearch(value));

const sortBy = (column: SortKey) => {
    const dir =
        props.filters.sort === column && props.filters.dir === 'asc'
            ? 'desc'
            : 'asc';
    visit({ sort: column, dir });
};

const sortIcon = (column: SortKey) => {
    if (props.filters.sort !== column) {
        return ArrowUpDown;
    }

    return props.filters.dir === 'asc' ? ArrowUp : ArrowDown;
};

const confirmDelete = () => {
    if (!productToDelete.value) {
        return;
    }

    router.delete(ProductController.destroy.url(productToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (productToDelete.value = null),
    });
};

const columns: { key: SortKey; label: string; class?: string }[] = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Name' },
    { key: 'price_cents', label: 'Price', class: 'text-right' },
    { key: 'stock', label: 'Stock', class: 'text-right' },
];
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col gap-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Products"
                description="Create, edit and remove products from the catalog."
            />
            <div class="flex gap-2">
                <Button as-child>
                    <Link :href="admin.products.create()">
                        <Plus class="size-4" />
                        New product
                    </Link>
                </Button>
            </div>
        </div>

        <div class="relative max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                type="search"
                class="pl-9"
                placeholder="Search by name or SKU"
                aria-label="Search products"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 font-medium"
                            :class="column.class"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 hover:text-foreground"
                                @click="sortBy(column.key)"
                            >
                                {{ column.label }}
                                <component
                                    :is="sortIcon(column.key)"
                                    class="size-3.5"
                                />
                            </button>
                        </th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 text-right font-medium">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products.data"
                        :key="product.id"
                        class="border-t transition-colors hover:bg-muted/30"
                    >
                        <td
                            class="px-4 py-3 font-mono text-xs whitespace-nowrap"
                        >
                            {{ product.sku }}
                        </td>
                        <td class="max-w-xs truncate px-4 py-3 font-medium">
                            {{ product.name }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ product.price.formatted }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <Badge
                                v-if="!product.in_stock"
                                variant="destructive"
                                >Out of stock</Badge
                            >
                            <span
                                v-else
                                :class="
                                    product.low_stock
                                        ? 'font-medium text-amber-600'
                                        : ''
                                "
                                >{{ product.stock }}</span
                            >
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <Badge variant="secondary">{{
                                product.category?.name
                            }}</Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    as-child
                                    :aria-label="`Edit ${product.name}`"
                                >
                                    <Link
                                        :href="admin.products.edit(product.id)"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Delete ${product.name}`"
                                    @click="productToDelete = product"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="products.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            No products found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :meta="products.meta"
            :links="products.links"
            item-label="products"
        />
    </div>

    <Dialog
        :open="productToDelete !== null"
        @update:open="(open) => !open && (productToDelete = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete product?</DialogTitle>
                <DialogDescription>
                    <strong>{{ productToDelete?.name }}</strong>
                    ({{ productToDelete?.sku }}) will be removed from the store.
                    Past orders keep their history.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>
                <Button variant="destructive" @click="confirmDelete"
                    >Delete</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
