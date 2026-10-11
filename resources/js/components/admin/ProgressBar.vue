<script setup lang="ts">
import { cn } from '@/lib/utils';

defineProps<{ value: number; failed?: boolean; label?: string }>();
</script>

<template>
    <div
        role="progressbar"
        :aria-valuenow="value"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-label="label ?? 'Progress'"
        class="h-2 w-full overflow-hidden rounded-full bg-muted"
    >
        <div
            :class="
                cn(
                    'h-full rounded-full transition-[width] duration-500',
                    failed ? 'bg-destructive' : 'bg-primary',
                )
            "
            :style="{ width: `${value}%` }"
        />
    </div>
</template>
