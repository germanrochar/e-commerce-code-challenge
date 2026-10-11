<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ShoppingBag, Trash2 } from '@lucide/vue';
import ProductImage from '@/components/store/ProductImage.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { home, login, register } from '@/routes';
import { show as checkout } from '@/routes/checkout';
import { destroy, update } from '@/routes/cart/items';
import { show as productShow } from '@/routes/products';
import type { Cart } from '@/types';

defineProps<{
    cart: Cart;
}>();

const page = usePage();

const changeQuantity = (productId: number, quantity: number) => {
    router.patch(update.url(productId), { quantity }, { preserveScroll: true });
};

const remove = (productId: number) => {
    router.delete(destroy.url(productId), { preserveScroll: true });
};
</script>

<template>
    <Head title="Cart" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Your cart</h1>
            <p class="mt-1 text-muted-foreground">
                {{ cart.count }}
                {{ cart.count === 1 ? 'item' : 'items' }}
            </p>
        </div>

        <Alert v-for="notice in cart.notices" :key="notice">
            <AlertTitle>Cart updated</AlertTitle>
            <AlertDescription>{{ notice }}</AlertDescription>
        </Alert>

        <div
            v-if="cart.lines.length === 0"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed px-6 py-16 text-center"
        >
            <div class="rounded-full bg-muted p-4">
                <ShoppingBag class="size-6 text-muted-foreground" />
            </div>
            <h2 class="text-lg font-semibold">Your cart is empty</h2>
            <p class="max-w-sm text-sm text-muted-foreground">
                Browse the catalog and add something you like.
            </p>
            <Button as-child>
                <Link :href="home()">Continue shopping</Link>
            </Button>
        </div>

        <template v-else>
            <ul class="divide-y rounded-2xl border">
                <li
                    v-for="line in cart.lines"
                    :key="line.product.id"
                    class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
                >
                    <Link
                        :href="productShow(line.product.sku)"
                        class="flex min-w-0 flex-1 items-center gap-4"
                    >
                        <ProductImage
                            :product="line.product"
                            class="size-20 shrink-0 rounded-xl"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ line.product.category?.name }}
                            </p>
                            <h2 class="truncate font-semibold">
                                {{ line.product.name }}
                            </h2>
                            <p class="text-sm text-muted-foreground">
                                {{ line.product.price.formatted }} each
                            </p>
                        </div>
                    </Link>

                    <div
                        class="flex items-center justify-between gap-4 sm:justify-end"
                    >
                        <label class="flex items-center gap-2 text-sm">
                            <span class="sr-only"
                                >Quantity for {{ line.product.name }}</span
                            >
                            <Input
                                type="number"
                                class="w-20"
                                :min="1"
                                :max="line.product.stock"
                                :model-value="line.quantity"
                                data-test="cart-quantity"
                                @change="
                                    changeQuantity(
                                        line.product.id,
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ),
                                    )
                                "
                            />
                        </label>
                        <p class="w-24 text-right font-semibold tabular-nums">
                            {{ line.line_total.formatted }}
                        </p>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove ${line.product.name}`"
                            data-test="cart-remove"
                            @click="remove(line.product.id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </li>
            </ul>

            <div
                class="flex flex-col gap-4 rounded-2xl border bg-card p-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm text-muted-foreground">Subtotal</p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ cart.total.formatted }}
                    </p>
                    <p
                        v-if="!page.props.auth.user"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        You'll be asked to
                        <Link
                            :href="login()"
                            class="underline underline-offset-4"
                            >log in</Link
                        >
                        or
                        <Link
                            :href="register()"
                            class="underline underline-offset-4"
                            >create an account</Link
                        >
                        to check out.
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <Button variant="outline" as-child>
                        <Link :href="home()">Continue shopping</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="checkout()" data-test="checkout">
                            Check out
                        </Link>
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
