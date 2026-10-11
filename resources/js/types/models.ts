export type Money = {
    cents: number;
    amount: string;
    currency: string;
    formatted: string;
};

export type CategorySummary = {
    name: string;
    slug: string;
};

export type CategoryFacet = CategorySummary & {
    count: number;
};

export type CatalogSort = 'relevance' | 'name' | 'price';

export type CatalogFilters = {
    q: string;
    category: string | null;
    sort: CatalogSort;
    dir: 'asc' | 'desc';
};

export type Product = {
    id: number;
    sku: string;
    name: string;
    description: string | null;
    price: Money;
    stock: number;
    in_stock: boolean;
    low_stock: boolean;
    weight_kg: string | null;
    image_url: string;
    category?: CategorySummary;
};

export type ImportStatus = 'queued' | 'processing' | 'completed' | 'failed';

export type ProductImport = {
    id: number;
    original_filename: string;
    status: ImportStatus;
    status_label: string;
    is_finished: boolean;
    progress: number;
    total_rows: number;
    processed_rows: number;
    created_count: number;
    duplicate_count: number;
    invalid_count: number;
    skipped_empty_count: number;
    rejected_count: number;
    failure_message: string | null;
    uploaded_by: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
};

export type ImportErrorType = 'validation' | 'duplicate';

export type ProductImportError = {
    id: number;
    row_number: number;
    sku: string | null;
    column: string | null;
    type: ImportErrorType;
    message: string;
    raw: Record<string, string | null>;
};

export type PageLink = {
    label: string;
    url: string | null;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        prev: string | null;
        next: string | null;
        pages: PageLink[];
    };
};
