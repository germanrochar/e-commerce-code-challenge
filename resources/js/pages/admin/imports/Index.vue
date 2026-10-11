<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ImportStatusBadge from '@/components/admin/ImportStatusBadge.vue';
import ProgressBar from '@/components/admin/ProgressBar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { Paginated, ProductImport } from '@/types';

const props = defineProps<{
    imports: Paginated<ProductImport>;
    requiredColumns: string[];
    maxUploadMb: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products', href: admin.products.index() },
            { title: 'Imports', href: admin.imports.index() },
        ],
    },
});

const form = useForm<{ file: File | null }>({ file: null });
const fileInput = ref<HTMLInputElement | null>(null);
const dragging = ref(false);

const hasRunningImports = computed(() =>
    props.imports.data.some((item) => !item.is_finished),
);

const { start, stop } = usePoll(
    2000,
    { only: ['imports'] },
    { autoStart: hasRunningImports.value },
);

watch(hasRunningImports, (running) => (running ? start() : stop()));

const selectFile = (file: File | undefined) => {
    form.clearErrors();
    form.file = file ?? null;
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;
    selectFile(event.dataTransfer?.files[0]);
};

const clearFile = () => {
    form.reset();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(admin.imports.store.url(), {
        forceFormData: true,
        onSuccess: () => clearFile(),
    });
};

const formatSize = (bytes: number) =>
    bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
</script>

<template>
    <Head title="Imports" />

    <div class="flex flex-col gap-8 px-4 py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Import products"
                description="Upload a CSV to add products in bulk. Large files are processed in the background."
            />
            <Button variant="outline" as-child>
                <a :href="admin.imports.template.url()">
                    <Download class="size-4" />
                    Download template
                </a>
            </Button>
        </div>

        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <label
                for="csv-file"
                :class="
                    cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors',
                        dragging
                            ? 'border-primary bg-primary/5'
                            : 'hover:border-primary/50 hover:bg-muted/40',
                    )
                "
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <div class="rounded-full bg-muted p-3">
                    <FileSpreadsheet class="size-6 text-muted-foreground" />
                </div>
                <div>
                    <p class="font-medium">
                        Drop a CSV file here or
                        <span class="text-primary underline">browse</span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Columns:
                        <code class="text-xs">{{
                            requiredColumns.join(', ')
                        }}</code>
                        <span class="ml-1">· up to {{ maxUploadMb }} MB</span>
                    </p>
                </div>
                <input
                    id="csv-file"
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="sr-only"
                    @change="
                        selectFile(
                            ($event.target as HTMLInputElement).files?.[0],
                        )
                    "
                />
            </label>

            <InputError :message="form.errors.file" />

            <div
                v-if="form.file"
                class="flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3"
            >
                <FileSpreadsheet class="size-5 text-muted-foreground" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ form.file.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatSize(form.file.size) }}
                    </p>
                    <ProgressBar
                        v-if="form.progress"
                        class="mt-2"
                        :value="form.progress.percentage ?? 0"
                        label="Upload progress"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Remove file"
                    :disabled="form.processing"
                    @click="clearFile"
                >
                    <X class="size-4" />
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    <Upload v-else class="size-4" />
                    Start import
                </Button>
            </div>
        </form>

        <section class="flex flex-col gap-4">
            <h3 class="text-lg font-semibold">Recent imports</h3>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">File</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Progress</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Created
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Rejected
                            </th>
                            <th class="px-4 py-3 font-medium">Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in imports.data"
                            :key="item.id"
                            class="border-t transition-colors hover:bg-muted/30"
                        >
                            <td class="max-w-xs truncate px-4 py-3 font-medium">
                                <Link
                                    :href="admin.imports.show(item.id)"
                                    class="hover:underline"
                                    >{{ item.original_filename }}</Link
                                >
                            </td>
                            <td class="px-4 py-3">
                                <ImportStatusBadge
                                    :status="item.status"
                                    :label="item.status_label"
                                />
                            </td>
                            <td class="w-40 px-4 py-3">
                                <ProgressBar
                                    :value="item.progress"
                                    :failed="item.status === 'failed'"
                                />
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ item.created_count.toLocaleString() }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ item.rejected_count.toLocaleString() }}
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                            >
                                {{ formatDate(item.created_at) }}
                                <span v-if="item.uploaded_by">
                                    · {{ item.uploaded_by }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="imports.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                No imports yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :meta="imports.meta"
                :links="imports.links"
                item-label="imports"
            />
        </section>
    </div>
</template>
