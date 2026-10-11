<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import OrderStatusBadge from '@/components/store/OrderStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { home } from '@/routes';
import { index } from '@/routes/orders';
import { show as productShow } from '@/routes/products';
import type { Order } from '@/types';

defineProps<{
    order: Order;
}>();
</script>

<template>
    <Head :title="`Order #${order.number}`" />

    <div class="mx-auto flex max-w-3xl flex-col gap-6">
        <nav
            aria-label="Breadcrumb"
            class="flex items-center gap-1 text-sm text-muted-foreground"
        >
            <Link :href="index()" class="hover:text-foreground">Orders</Link>
            <ChevronRight class="size-4" />
            <span class="text-foreground">#{{ order.number }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">
                    Order #{{ order.number }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Placed {{ formatDateTime(order.placed_at) }}
                </p>
            </div>
            <OrderStatusBadge
                :status="order.status"
                :label="order.status_label"
            />
        </div>

        <ul class="divide-y rounded-2xl border">
            <li
                v-for="item in order.items ?? []"
                :key="item.id"
                class="flex items-center justify-between gap-4 p-4"
            >
                <div class="min-w-0">
                    <p class="font-medium">
                        <Link
                            v-if="item.product_sku"
                            :href="productShow(item.product_sku)"
                            class="hover:underline"
                        >
                            {{ item.name }}
                        </Link>
                        <span v-else>{{ item.name }}</span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.sku }} · Qty {{ item.quantity }} ·
                        {{ item.unit_price.formatted }} each
                    </p>
                </div>
                <p class="font-semibold tabular-nums">
                    {{ item.line_total.formatted }}
                </p>
            </li>
        </ul>

        <div class="rounded-2xl border bg-card p-6">
            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Total</dt>
                    <dd class="text-lg font-semibold tabular-nums">
                        {{ order.total.formatted }}
                    </dd>
                </div>
                <div v-if="order.card" class="flex justify-between">
                    <dt class="text-muted-foreground">Payment</dt>
                    <dd>{{ order.card }}</dd>
                </div>
                <div v-if="order.paid_at" class="flex justify-between">
                    <dt class="text-muted-foreground">Paid</dt>
                    <dd>{{ formatDateTime(order.paid_at) }}</dd>
                </div>
            </dl>
        </div>

        <Button variant="outline" as-child class="self-start">
            <Link :href="home()">Continue shopping</Link>
        </Button>
    </div>
</template>
