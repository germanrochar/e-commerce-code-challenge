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
