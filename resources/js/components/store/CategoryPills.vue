<script setup lang="ts">
import { LayoutGrid } from '@lucide/vue';
import { categoryIcon } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { CategoryFacet } from '@/types';

defineProps<{
    categories: CategoryFacet[];
    active: string | null;
    total: number;
}>();

const emit = defineEmits<{ select: [slug: string | null] }>();

const pillClass = (selected: boolean) =>
    cn(
        'inline-flex shrink-0 snap-start items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium whitespace-nowrap transition-all',
        selected
            ? 'border-primary bg-primary text-primary-foreground shadow-sm'
            : 'bg-background hover:border-foreground/30 hover:bg-muted',
    );
</script>

<template>
    <div
        class="-mx-4 flex snap-x [scrollbar-width:none] gap-2 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 [&::-webkit-scrollbar]:hidden"
        role="tablist"
        aria-label="Categories"
    >
        <button
            type="button"
            role="tab"
            :aria-selected="active === null"
            :class="pillClass(active === null)"
            @click="emit('select', null)"
        >
            <LayoutGrid class="size-4" />
            All
            <span class="text-xs opacity-70">{{ total }}</span>
        </button>
        <button
            v-for="category in categories"
            :key="category.slug"
            type="button"
            role="tab"
            :aria-selected="active === category.slug"
            :class="pillClass(active === category.slug)"
            @click="emit('select', category.slug)"
        >
            <component :is="categoryIcon(category.slug)" class="size-4" />
            {{ category.name }}
            <span class="text-xs opacity-70">{{ category.count }}</span>
        </button>
    </div>
</template>
