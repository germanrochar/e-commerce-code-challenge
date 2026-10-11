<script setup lang="ts">
import { Check, LayoutGrid } from '@lucide/vue';
import { categoryIcon } from '@/lib/catalog';
import { cn } from '@/lib/utils';
import type { CategoryFacet } from '@/types';

defineProps<{
    categories: CategoryFacet[];
    active: string | null;
    total: number;
}>();

const emit = defineEmits<{ select: [slug: string | null] }>();

const itemClass = (selected: boolean) =>
    cn(
        'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors',
        selected
            ? 'bg-primary/10 font-medium text-foreground'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );
</script>

<template>
    <nav aria-label="Filter by category" class="flex flex-col gap-0.5">
        <button
            type="button"
            :class="itemClass(active === null)"
            :aria-pressed="active === null"
            @click="emit('select', null)"
        >
            <LayoutGrid class="size-4 shrink-0" />
            <span class="flex-1">All categories</span>
            <span class="text-xs tabular-nums">{{ total }}</span>
        </button>
        <button
            v-for="category in categories"
            :key="category.slug"
            type="button"
            :class="itemClass(active === category.slug)"
            :aria-pressed="active === category.slug"
            @click="emit('select', category.slug)"
        >
            <component
                :is="categoryIcon(category.slug)"
                class="size-4 shrink-0"
            />
            <span class="flex-1 truncate">{{ category.name }}</span>
            <Check
                v-if="active === category.slug"
                class="size-4 text-primary"
            />
            <span v-else class="text-xs tabular-nums">{{
                category.count
            }}</span>
        </button>
    </nav>
</template>
