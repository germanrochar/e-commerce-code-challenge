<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import ProductForm from '@/components/admin/ProductForm.vue';
import Heading from '@/components/Heading.vue';
import admin from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: admin.products.index() },
            { title: 'New product', href: admin.products.create() },
        ],
    },
});

defineProps<{
    categories: string[];
}>();
</script>

<template>
    <Head title="New product" />

    <div class="px-4 py-6">
        <Heading
            title="New product"
            description="Add a product to the catalog."
        />

        <ProductForm
            :form="ProductController.store.form()"
            :categories="categories"
            submit-label="Create product"
        />
    </div>
</template>
