import {
    Blocks,
    BookOpen,
    Coffee,
    CookingPot,
    Cpu,
    Dumbbell,
    Flower2,
    Footprints,
    Gamepad2,
    Gift,
    HeartPulse,
    Lamp,
    Package,
    PawPrint,
    PenLine,
    Shirt,
    Sparkles,
    Tent,
    Watch,
    Wrench,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';

const icons: Record<string, LucideIcon> = {
    accessories: Watch,
    beauty: Sparkles,
    books: BookOpen,
    clothing: Shirt,
    electronics: Cpu,
    'food-beverage': Coffee,
    footwear: Footprints,
    games: Gamepad2,
    garden: Flower2,
    gifts: Gift,
    health: HeartPulse,
    'home-office': Lamp,
    kitchen: CookingPot,
    outdoors: Tent,
    pets: PawPrint,
    sports: Dumbbell,
    stationery: PenLine,
    tools: Wrench,
    toys: Blocks,
};

export function categoryIcon(slug: string | null | undefined): LucideIcon {
    return (slug && icons[slug]) || Package;
}

export function categoryHue(slug: string | null | undefined): number {
    let hash = 0;

    for (const char of slug ?? '') {
        hash = (hash * 31 + char.charCodeAt(0)) % 360;
    }

    return hash;
}

export const sortOptions = [
    { value: 'relevance:asc', label: 'Best match' },
    { value: 'name:asc', label: 'Name: A to Z' },
    { value: 'name:desc', label: 'Name: Z to A' },
    { value: 'price:asc', label: 'Price: low to high' },
    { value: 'price:desc', label: 'Price: high to low' },
] as const;
