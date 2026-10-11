<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{ count?: number }>();
</script>

<template>
    <div
        v-for="index in count ?? 6"
        :key="index"
        class="overflow-hidden rounded-2xl border"
        aria-hidden="true"
    >
        <Skeleton class="aspect-square rounded-none" />
        <div class="space-y-2 p-4">
            <Skeleton class="h-3 w-1/3" />
            <Skeleton class="h-4 w-3/4" />
            <Skeleton class="mt-4 h-5 w-1/4" />
        </div>
    </div>
</template>
