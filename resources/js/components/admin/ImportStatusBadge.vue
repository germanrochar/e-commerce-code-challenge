<script setup lang="ts">
import { CircleCheck, CircleX, Clock, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { ImportStatus } from '@/types';

const props = defineProps<{ status: ImportStatus; label: string }>();

const variants = {
    queued: { icon: Clock, class: 'bg-muted text-muted-foreground' },
    processing: {
        icon: LoaderCircle,
        class: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    },
    completed: {
        icon: CircleCheck,
        class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
    failed: {
        icon: CircleX,
        class: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
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
        <component
            :is="variant.icon"
            :class="cn('size-3.5', status === 'processing' && 'animate-spin')"
        />
        {{ label }}
    </span>
</template>
