<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import ProductImage from '@/components/store/ProductImage.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index as cartIndex } from '@/routes/cart';
import { store } from '@/routes/checkout';
import { show as productShow } from '@/routes/products';
import type { Cart, TestCard } from '@/types';

defineProps<{
    cart: Cart;
    idempotencyKey: string;
    testCards: TestCard[];
}>();

const card = reactive({
    number: '',
    expiry: '',
    cvc: '',
});

const fillCard = (number: string) => {
    card.number = number;
    card.expiry = '12/34';
    card.cvc = '123';
};

const formatCardNumber = (value: string) =>
    value
        .replace(/\D/g, '')
        .slice(0, 19)
        .replace(/(.{4})/g, '$1 ')
        .trim();
</script>

<template>
    <Head title="Checkout" />

    <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[1fr_20rem]">
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Checkout</h1>
                <p class="mt-1 text-muted-foreground">
                    Payments are simulated. No real card is charged.
                </p>
            </div>

            <Form
                v-bind="store.form()"
                class="flex flex-col gap-6"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="idempotency_key"
                    :value="idempotencyKey"
                />

                <div
                    v-if="errors.checkout"
                    class="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                    data-test="checkout-error"
                >
                    {{ errors.checkout }}
                </div>

                <fieldset class="grid gap-4 rounded-2xl border p-6">
                    <legend class="px-1 text-sm font-semibold">
                        Card details
                    </legend>

                    <div class="grid gap-2">
                        <Label for="card_number">Card number</Label>
                        <Input
                            id="card_number"
                            name="card_number"
                            inputmode="numeric"
                            autocomplete="cc-number"
                            placeholder="4242 4242 4242 4242"
                            :model-value="card.number"
                            data-test="card-number"
                            @update:model-value="
                                card.number = formatCardNumber(String($event))
                            "
                        />
                        <InputError :message="errors.card_number" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="expiry">Expiry</Label>
                            <Input
                                id="expiry"
                                name="expiry"
                                inputmode="numeric"
                                autocomplete="cc-exp"
                                placeholder="MM/YY"
                                maxlength="7"
                                v-model="card.expiry"
                                data-test="card-expiry"
                            />
                            <InputError :message="errors.expiry" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="cvc">CVC</Label>
                            <Input
                                id="cvc"
                                name="cvc"
                                inputmode="numeric"
                                autocomplete="cc-csc"
                                placeholder="123"
                                maxlength="4"
                                v-model="card.cvc"
                                data-test="card-cvc"
                            />
                            <InputError :message="errors.cvc" />
                        </div>
                    </div>
                </fieldset>

                <div v-if="testCards.length" class="grid gap-2">
                    <p class="text-sm font-medium">Test cards</p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="testCard in testCards"
                            :key="testCard.number"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="fillCard(testCard.number)"
                        >
                            {{ testCard.outcome }}
                        </Button>
                    </div>
                </div>

                <Button
                    type="submit"
                    size="lg"
                    :disabled="processing"
                    data-test="pay"
                >
                    <Spinner v-if="processing" />
                    <Lock v-else class="size-4" />
                    Pay {{ cart.total.formatted }}
                </Button>
            </Form>
        </div>

        <aside class="flex h-fit flex-col gap-4 rounded-2xl border bg-card p-6">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Order summary</h2>
                <Link
                    :href="cartIndex()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    Edit cart
                </Link>
            </div>

            <ul class="divide-y">
                <li
                    v-for="line in cart.lines"
                    :key="line.product.id"
                    class="flex gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <Link
                        :href="productShow(line.product.sku)"
                        class="shrink-0"
                    >
                        <ProductImage
                            :product="line.product"
                            class="size-14 rounded-lg"
                        />
                    </Link>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ line.product.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Qty {{ line.quantity }}
                        </p>
                    </div>
                    <p class="text-sm font-medium tabular-nums">
                        {{ line.line_total.formatted }}
                    </p>
                </li>
            </ul>

            <div
                class="flex items-center justify-between border-t pt-4 text-lg font-semibold"
            >
                <span>Total</span>
                <span class="tabular-nums">{{ cart.total.formatted }}</span>
            </div>
        </aside>
    </div>
</template>
