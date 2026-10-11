<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import Pagination from '@/components/Pagination.vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { home } from '@/routes';
import { show } from '@/routes/orders';
import type { Order, Paginated } from '@/types';

defineProps<{
    orders: Paginated<Order>;
}>();
</script>

<template>
    <Head title="Orders" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Your orders</h1>
            <p class="mt-1 text-muted-foreground">
                Confirmation emails are sent when a payment is approved.
            </p>
        </div>

        <div
            v-if="orders.data.length === 0"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-16 text-center"
        >
            <div class="rounded-full bg-muted p-4">
                <Package class="size-6 text-muted-foreground" />
            </div>
            <h2 class="text-lg font-semibold">No orders yet</h2>
            <p class="max-w-sm text-sm text-muted-foreground">
                When you check out, your orders will show up here.
            </p>
            <Button as-child>
                <Link :href="home()">Browse products</Link>
            </Button>
        </div>

        <ul v-else class="divide-y rounded-2xl border">
            <li v-for="order in orders.data" :key="order.key">
                <Link
                    :href="show(order.key)"
                    class="flex flex-col gap-2 p-4 transition-colors hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="font-semibold">Order #{{ order.number }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatDateTime(order.placed_at) }}
                            <template v-if="order.item_count">
                                ·
                                {{ order.item_count }}
                                {{ order.item_count === 1 ? 'item' : 'items' }}
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <OrderStatusBadge
                            :status="order.status"
                            :label="order.status_label"
                        />
                        <p class="font-semibold tabular-nums">
                            {{ order.total.formatted }}
                        </p>
                    </div>
                </Link>
            </li>
        </ul>

        <Pagination
            v-if="orders.data.length > 0"
            :meta="orders.meta"
            :links="orders.links"
            item-label="orders"
        />
    </div>
</template>
