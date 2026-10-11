<script setup lang="ts">
import { CircleCheck, CircleX, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { OrderStatus } from '@/types';

const props = defineProps<{ status: OrderStatus; label: string }>();

const variants = {
    pending: {
        icon: Clock,
        class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    },
    paid: {
        icon: CircleCheck,
        class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
    failed: {
        icon: CircleX,
        class: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    },
    expired: {
        icon: Clock,
        class: 'bg-muted text-muted-foreground',
    },
} as const;

const variant = computed(() => variants[props.status]);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                variant.class,
            )
        "
    >
        <component :is="variant.icon" class="size-3.5" />
        {{ label }}
    </span>
</template>
