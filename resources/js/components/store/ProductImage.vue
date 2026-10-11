<script setup lang="ts">
import { computed } from 'vue';
import { categoryHue, categoryIcon } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { Product } from '@/types';

const props = defineProps<{ product: Product; class?: string }>();

const hue = computed(() => categoryHue(props.product.category?.slug));
const icon = computed(() => categoryIcon(props.product.category?.slug));
</script>

<template>
    <div
        :class="
            cn(
                'relative flex aspect-square items-center justify-center overflow-hidden',
                props.class,
            )
        "
        :style="{
            background: `linear-gradient(135deg, hsl(${hue} 70% 94%), hsl(${(hue + 40) % 360} 65% 86%))`,
        }"
    >
        <img
            :src="product.image_url"
            :alt="product.name"
            loading="lazy"
            class="size-3/4 object-contain"
        />
        <component
            :is="icon"
            class="absolute right-3 bottom-3 size-5 text-slate-900/30"
            aria-hidden="true"
        />
    </div>
</template>
