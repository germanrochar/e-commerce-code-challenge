<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { home, login, register } from '@/routes';

const page = usePage();

const currentTerm = () => {
    const filters = page.props.filters as { q?: string } | undefined;

    return filters?.q ?? '';
};

const term = ref(currentTerm());

watch(
    () => page.props.filters,
    () => (term.value = currentTerm()),
);

const submit = () => {
    const q = term.value.trim();

    router.get(home.url(), q ? { q } : {});
};
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b bg-background/85 backdrop-blur supports-[backdrop-filter]:bg-background/70"
    >
        <div
            class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6"
        >
            <Link :href="home()" class="flex items-center gap-1">
                <AppLogo />
            </Link>

            <form
                role="search"
                class="order-last w-full md:order-none md:max-w-xl md:flex-1"
                @submit.prevent="submit"
            >
                <label class="relative block">
                    <span class="sr-only">Search products</span>
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="term"
                        type="search"
                        name="q"
                        placeholder="Search for shoes, coffee, speakers…"
                        class="h-10 rounded-full pl-9"
                    />
                </label>
            </form>

            <nav class="ml-auto flex items-center gap-2">
                <slot name="actions" />

                <DropdownMenu v-if="page.props.auth.user">
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="h-10 gap-2 px-2">
                            <UserInfo :user="page.props.auth.user" />
                            <ChevronDown class="size-4 opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60">
                        <UserMenuContent :user="page.props.auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>

                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child class="hidden sm:inline-flex">
                        <Link :href="register()">Create account</Link>
                    </Button>
                </template>
            </nav>
        </div>
    </header>
</template>
