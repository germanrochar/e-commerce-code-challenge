<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import ProductForm from '@/components/admin/ProductForm.vue';
import Heading from '@/components/Heading.vue';
import admin from '@/routes/admin';
import type { Product } from '@/types';

const props = defineProps<{
    product: Product;
    categories: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: admin.products.index() },
            { title: 'Edit product', href: admin.products.index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${props.product.name}`" />

    <div class="px-4 py-6">
        <Heading
            :title="`Edit ${product.name}`"
            :description="`SKU ${product.sku}`"
        />

        <ProductForm
            :form="ProductController.update.form(product.id)"
            :categories="categories"
            :product="product"
            submit-label="Save changes"
        />
    </div>
</template>
