<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/cart/items';
import type { Product } from '@/types';

const props = withDefaults(
    defineProps<{
        product: Product;
        quantity?: number;
        label?: string;
        size?: 'default' | 'sm' | 'lg';
    }>(),
    {
        quantity: 1,
        label: 'Add to cart',
        size: 'default',
    },
);

const adding = ref(false);

const add = () => {
    if (!props.product.in_stock) {
        return;
    }

    router.post(
        store.url(),
        {
            product_id: props.product.id,
            quantity: props.quantity,
        },
        {
            preserveScroll: true,
            onStart: () => (adding.value = true),
            onFinish: () => (adding.value = false),
        },
    );
};
</script>

<template>
    <Button
        type="button"
        :size="size"
        :disabled="!product.in_stock || adding"
        data-test="add-to-cart"
        @click.stop="add"
    >
        <Spinner v-if="adding" />
        <ShoppingCart v-else class="size-4" />
        {{ product.in_stock ? label : 'Out of stock' }}
    </Button>
</template>
