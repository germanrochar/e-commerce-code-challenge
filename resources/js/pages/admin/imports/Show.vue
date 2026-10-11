<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { CircleAlert, Download } from '@lucide/vue';
import { computed, watch } from 'vue';
import ImportStatusBadge from '@/components/admin/ImportStatusBadge.vue';
import ProgressBar from '@/components/admin/ProgressBar.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type {
    ImportErrorType,
    Paginated,
    ProductImport,
    ProductImportError,
} from '@/types';

const props = defineProps<{
    import: ProductImport;
    errors: Paginated<ProductImportError>;
    filters: { type: ImportErrorType | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: admin.products.index() },
            { title: 'Imports', href: admin.imports.index() },
            { title: 'Import details' },
        ],
    },
});

const record = computed(() => props.import);

const { start, stop } = usePoll(
    2000,
    { only: ['import', 'errors'] },
    { autoStart: !props.import.is_finished },
);

watch(
    () => record.value.is_finished,
    (finished) => (finished ? stop() : start()),
);

const stats = computed(() => [
    {
        label: 'Rows in file',
        value: record.value.total_rows,
        class: '',
    },
    {
        label: 'Created',
        value: record.value.created_count,
        class: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        label: 'Duplicates',
        value: record.value.duplicate_count,
        class: 'text-amber-600 dark:text-amber-400',
    },
    {
        label: 'Invalid',
        value: record.value.invalid_count,
        class: 'text-red-600 dark:text-red-400',
    },
    {
        label: 'Empty rows skipped',
        value: record.value.skipped_empty_count,
        class: 'text-muted-foreground',
    },
]);

const tabs: { label: string; type: ImportErrorType | null }[] = [
    { label: 'All problems', type: null },
    { label: 'Invalid', type: 'validation' },
    { label: 'Duplicates', type: 'duplicate' },
];

const filterBy = (type: ImportErrorType | null) => {
    router.get(admin.imports.show.url(record.value.id), type ? { type } : {}, {
        only: ['errors', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head :title="`Import · ${record.original_filename}`" />

    <div class="flex flex-col gap-6 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="record.original_filename"
                :description="
                    record.uploaded_by
                        ? `Uploaded by ${record.uploaded_by}`
                        : 'Imported from the command line'
                "
            />
            <div class="flex items-center gap-3">
                <ImportStatusBadge
                    :status="record.status"
                    :label="record.status_label"
                />
                <Button
                    v-if="record.is_finished && record.rejected_count > 0"
                    variant="outline"
                    as-child
                >
                    <a :href="admin.imports.rejected.url(record.id)">
                        <Download class="size-4" />
                        Rejected rows (CSV)
                    </a>
                </Button>
            </div>
        </div>

        <Alert v-if="record.failure_message" variant="destructive">
            <CircleAlert class="size-4" />
            <AlertTitle>Import failed</AlertTitle>
            <AlertDescription>{{ record.failure_message }}</AlertDescription>
        </Alert>

        <div class="flex flex-col gap-2">
            <div class="flex justify-between text-sm text-muted-foreground">
                <span
                    >{{ record.processed_rows.toLocaleString() }} of
                    {{ record.total_rows.toLocaleString() }} rows
                    processed</span
                >
                <span class="font-medium text-foreground tabular-nums"
                    >{{ record.progress }}%</span
                >
            </div>
            <ProgressBar
                :value="record.progress"
                :failed="record.status === 'failed'"
                label="Import progress"
            />
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-xl border p-4"
            >
                <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
                <p
                    :class="
                        cn(
                            'mt-1 text-2xl font-semibold tabular-nums',
                            stat.class,
                        )
                    "
                >
                    {{ stat.value.toLocaleString() }}
                </p>
            </div>
        </div>

        <section class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">Rejected rows</h3>
                <div class="flex rounded-lg border p-1" role="tablist">
                    <button
                        v-for="tab in tabs"
                        :key="tab.label"
                        type="button"
                        role="tab"
                        :aria-selected="filters.type === tab.type"
                        :class="
                            cn(
                                'rounded-md px-3 py-1 text-sm transition-colors',
                                filters.type === tab.type
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        @click="filterBy(tab.type)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Row</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium">Name in file</th>
                            <th class="px-4 py-3 font-medium">Column</th>
                            <th class="px-4 py-3 font-medium">Problem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="error in errors.data"
                            :key="error.id"
                            class="border-t align-top"
                        >
                            <td class="px-4 py-3 tabular-nums">
                                {{ error.row_number }}
                            </td>
                            <td
                                class="px-4 py-3 font-mono text-xs whitespace-nowrap"
                            >
                                {{ error.sku ?? '—' }}
                            </td>
                            <td
                                class="max-w-56 truncate px-4 py-3"
                                :title="error.raw.name ?? ''"
                            >
                                {{ error.raw.name?.trim() || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        error.type === 'duplicate'
                                            ? 'secondary'
                                            : 'destructive'
                                    "
                                    >{{ error.column }}</Badge
                                >
                            </td>
                            <td class="px-4 py-3">{{ error.message }}</td>
                        </tr>
                        <tr v-if="errors.data.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                {{
                                    record.is_finished
                                        ? 'No rejected rows. Every row was imported.'
                                        : 'No problems found so far.'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :meta="errors.meta"
                :links="errors.links"
                item-label="problems"
            />
        </section>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="admin.imports.index()">← All imports</Link>
            </Button>
        </div>
    </div>
</template>
