<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { Product } from '@/types';

const props = defineProps<{ product: Product; class?: string }>();
</script>

<template>
    <span
        v-if="!product.in_stock"
        :class="
            cn(
                'rounded-full bg-slate-900/80 px-2.5 py-1 text-xs font-medium text-white backdrop-blur',
                props.class,
            )
        "
        >Out of stock</span
    >
    <span
        v-else-if="product.low_stock"
        :class="
            cn(
                'rounded-full bg-amber-500/90 px-2.5 py-1 text-xs font-medium text-white backdrop-blur',
                props.class,
            )
        "
        >Only {{ product.stock }} left</span
    >
</template>
