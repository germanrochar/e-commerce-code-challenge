<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AddToCartButton from '@/components/store/AddToCartButton.vue';
import ProductImage from '@/components/store/ProductImage.vue';
import StockBadge from '@/components/store/StockBadge.vue';
import { show } from '@/routes/products';
import type { Product } from '@/types';

defineProps<{ product: Product }>();
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded-2xl border bg-card transition-shadow hover:shadow-lg"
    >
        <Link :href="show(product.sku)" class="relative block" prefetch>
            <ProductImage
                :product="product"
                class="transition-transform duration-300 group-hover:scale-[1.03]"
            />
            <StockBadge :product="product" class="absolute top-3 left-3" />
        </Link>
        <div class="flex flex-1 flex-col gap-1 p-4">
            <p
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ product.category?.name }}
            </p>
            <h3 class="line-clamp-2 font-semibold">
                <Link :href="show(product.sku)" class="hover:underline">
                    {{ product.name }}
                </Link>
            </h3>
            <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                <p class="text-lg font-semibold tabular-nums">
                    {{ product.price.formatted }}
                </p>
                <AddToCartButton :product="product" size="sm" label="Add" />
            </div>
        </div>
    </article>
</template>
