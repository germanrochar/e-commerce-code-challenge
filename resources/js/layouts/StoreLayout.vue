<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import StoreHeader from '@/components/store/StoreHeader.vue';
import { Toaster } from '@/components/ui/sonner';

const name = usePage().props.name;
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background">
        <StoreHeader />
        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6">
            <slot />
        </main>
        <footer class="border-t">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:justify-between sm:px-6"
            >
                <p>© {{ new Date().getFullYear() }} {{ name }}</p>
                <p>Payments are simulated. No real cards are charged.</p>
            </div>
        </footer>
        <Toaster />
    </div>
</template>
