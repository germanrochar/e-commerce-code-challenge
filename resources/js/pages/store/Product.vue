<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, PackageCheck, Ruler, Truck } from '@lucide/vue';
import { computed } from 'vue';
import ProductCard from '@/components/store/ProductCard.vue';
import ProductImage from '@/components/store/ProductImage.vue';
import StockBadge from '@/components/store/StockBadge.vue';
import { home } from '@/routes';
import type { Product } from '@/types';

const props = defineProps<{
    product: Product;
    related: Product[];
}>();

const availability = computed(() => {
    if (!props.product.in_stock) {
        return { text: 'Out of stock', class: 'text-muted-foreground' };
    }

    if (props.product.low_stock) {
        return {
            text: `Only ${props.product.stock} left in stock`,
            class: 'text-amber-600 dark:text-amber-400',
        };
    }

    return {
        text: 'In stock, ready to ship',
        class: 'text-emerald-600 dark:text-emerald-400',
    };
});
</script>

<template>
    <Head :title="product.name" />

    <nav
        aria-label="Breadcrumb"
        class="mb-6 flex items-center gap-1 text-sm text-muted-foreground"
    >
        <Link :href="home()" class="hover:text-foreground">Store</Link>
        <template v-if="product.category">
            <ChevronRight class="size-4" />
            <Link
                :href="home({ query: { category: product.category.slug } })"
                class="hover:text-foreground"
                >{{ product.category.name }}</Link
            >
        </template>
        <ChevronRight class="size-4" />
        <span class="truncate text-foreground">{{ product.name }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-2 lg:gap-12">
        <div class="relative">
            <ProductImage :product="product" class="rounded-3xl" />
            <StockBadge :product="product" class="absolute top-4 left-4" />
        </div>

        <div class="flex flex-col gap-6">
            <div>
                <p
                    class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ product.category?.name }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight">
                    {{ product.name }}
                </h1>
                <p class="mt-1 font-mono text-xs text-muted-foreground">
                    SKU {{ product.sku }}
                </p>
            </div>

            <p class="text-3xl font-semibold tabular-nums">
                {{ product.price.formatted }}
            </p>

            <p
                :class="[
                    'flex items-center gap-2 text-sm font-medium',
                    availability.class,
                ]"
            >
                <PackageCheck class="size-4" />
                {{ availability.text }}
            </p>

            <slot name="purchase" />

            <p
                v-if="product.description"
                class="leading-relaxed whitespace-pre-line text-muted-foreground"
            >
                {{ product.description }}
            </p>

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl border p-4">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Ruler class="size-4" />
                        Weight
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{
                            product.weight_kg
                                ? `${Number(product.weight_kg)} kg`
                                : 'Not specified'
                        }}
                    </dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="flex items-center gap-2 text-muted-foreground">
                        <Truck class="size-4" />
                        Shipping
                    </dt>
                    <dd class="mt-1 font-medium">Free over $50</dd>
                </div>
            </dl>
        </div>
    </div>

    <section v-if="related.length" class="mt-16">
        <h2 class="mb-4 text-xl font-semibold">
            More in {{ product.category?.name }}
        </h2>
        <div class="grid grid-cols-2 gap-4 sm:gap-6 md:grid-cols-4">
            <ProductCard
                v-for="item in related"
                :key="item.id"
                :product="item"
            />
        </div>
    </section>
</template>
