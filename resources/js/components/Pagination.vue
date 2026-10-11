<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

defineProps<{
    meta: Paginated<unknown>['meta'];
    links: Paginated<unknown>['links'];
    itemLabel?: string;
}>();

const baseClass =
    'inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm transition-colors';
</script>

<template>
    <nav
        v-if="meta.last_page > 1 || meta.total > 0"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
        aria-label="Pagination"
    >
        <p class="text-sm text-muted-foreground">
            Showing
            <span class="font-medium text-foreground">{{
                meta.from ?? 0
            }}</span>
            –
            <span class="font-medium text-foreground">{{ meta.to ?? 0 }}</span>
            of
            <span class="font-medium text-foreground">{{ meta.total }}</span>
            {{ itemLabel ?? 'results' }}
        </p>

        <div v-if="meta.last_page > 1" class="flex items-center gap-1">
            <Link
                v-if="links.prev"
                :href="links.prev"
                preserve-scroll
                :class="cn(baseClass, 'hover:bg-accent')"
                aria-label="Previous page"
                data-test="pagination-prev"
            >
                <ChevronLeft class="size-4" />
            </Link>
            <span
                v-else
                :class="cn(baseClass, 'pointer-events-none opacity-40')"
            >
                <ChevronLeft class="size-4" />
            </span>

            <template v-for="(page, index) in links.pages" :key="index">
                <span
                    v-if="!page.url"
                    class="px-2 text-sm text-muted-foreground"
                    >{{ page.label }}</span
                >
                <Link
                    v-else
                    :href="page.url"
                    preserve-scroll
                    :aria-current="page.active ? 'page' : undefined"
                    :class="
                        cn(
                            baseClass,
                            page.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-accent',
                        )
                    "
                    >{{ page.label }}</Link
                >
            </template>

            <Link
                v-if="links.next"
                :href="links.next"
                preserve-scroll
                :class="cn(baseClass, 'hover:bg-accent')"
                aria-label="Next page"
                data-test="pagination-next"
            >
                <ChevronRight class="size-4" />
            </Link>
            <span
                v-else
                :class="cn(baseClass, 'pointer-events-none opacity-40')"
            >
                <ChevronRight class="size-4" />
            </span>
        </div>
    </nav>
</template>
