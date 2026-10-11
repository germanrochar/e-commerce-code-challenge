<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';
import type { Product } from '@/types';

type FormDefinition = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

const props = defineProps<{
    form: FormDefinition;
    categories: string[];
    product?: Product;
    submitLabel: string;
}>();

const textareaClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Form
        v-bind="props.form"
        class="grid max-w-3xl gap-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="product?.name"
                    required
                    maxlength="255"
                    placeholder="Running Shoes"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="sku">SKU</Label>
                <Input
                    id="sku"
                    name="sku"
                    :default-value="product?.sku"
                    required
                    maxlength="64"
                    class="font-mono uppercase"
                    placeholder="RS-001"
                />
                <InputError :message="errors.sku" />
            </div>

            <div class="grid gap-2">
                <Label for="category">Category</Label>
                <Input
                    id="category"
                    name="category"
                    :default-value="product?.category?.name"
                    required
                    list="category-options"
                    maxlength="100"
                    placeholder="Footwear"
                />
                <datalist id="category-options">
                    <option
                        v-for="category in categories"
                        :key="category"
                        :value="category"
                    />
                </datalist>
                <p class="text-xs text-muted-foreground">
                    Pick an existing category or type a new one.
                </p>
                <InputError :message="errors.category" />
            </div>

            <div class="grid gap-2">
                <Label for="price">Price (USD)</Label>
                <Input
                    id="price"
                    name="price"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :default-value="product?.price.amount"
                    required
                    placeholder="89.99"
                />
                <InputError :message="errors.price" />
            </div>

            <div class="grid gap-2">
                <Label for="stock">Stock</Label>
                <Input
                    id="stock"
                    name="stock"
                    type="number"
                    step="1"
                    min="0"
                    :default-value="product?.stock ?? 0"
                    required
                />
                <InputError :message="errors.stock" />
            </div>

            <div class="grid gap-2">
                <Label for="weight_kg">Weight (kg)</Label>
                <Input
                    id="weight_kg"
                    name="weight_kg"
                    type="number"
                    step="0.001"
                    min="0"
                    :default-value="product?.weight_kg ?? undefined"
                    placeholder="0.35"
                />
                <InputError :message="errors.weight_kg" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="5000"
                    :class="textareaClass"
                    :value="product?.description ?? ''"
                    placeholder="Lightweight running shoes for daily training"
                ></textarea>
                <InputError :message="errors.description" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="processing" data-test="save-product-button">
                {{ submitLabel }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="admin.products.index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
