<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ThemeLocaleToggle from './ThemeLocaleToggle.vue';
import Toast from './Toast.vue';
import Button from './Button.vue';

const props = defineProps({
    title: { type: String, default: '' },
    variant: {
        type: String,
        default: 'app',
        validator: (v) => ['app', 'admin', 'owner', 'user', 'guest'].includes(v),
    },
});

const page = usePage();
const { t } = useI18n();
const mobileOpen = ref(false);

const user = computed(() => page.props.auth?.user || null);
const role = computed(() => {
    const raw = page.props.auth?.role;
    if (!raw) return 'guest';
    return typeof raw === 'string' ? raw.toLowerCase() : String(raw).toLowerCase();
});

const menus = computed(() => {
    const map = {
        guest: [
            { label: t('nav.home'), href: '/' },
            { label: t('nav.spaces'), href: '/spaces' },
            { label: t('nav.login'), href: '/login' },
            { label: t('nav.register'), href: '/register' },
        ],
        user: [
            { label: t('nav.dashboard'), href: '/user/dashboard' },
            { label: t('nav.spaces'), href: '/spaces' },
            { label: t('nav.bookings'), href: '/user/bookings' },
            { label: t('nav.profile'), href: '/profile' },
        ],
        owner: [
            { label: t('nav.dashboard'), href: '/owner/dashboard' },
            { label: t('nav.workspaces'), href: '/owner/workspaces' },
            { label: t('nav.bookings'), href: '/owner/bookings' },
            { label: t('nav.offers'), href: '/owner/offers' },
            { label: t('nav.profile'), href: '/profile' },
        ],
        admin: [
            { label: t('nav.dashboard'), href: '/admin/dashboard' },
            { label: t('nav.users'), href: '/admin/users' },
            { label: t('nav.workspaces'), href: '/admin/workspaces' },
            { label: t('nav.bookings'), href: '/admin/bookings' },
            { label: t('nav.reports'), href: '/admin/reports' },
            { label: t('nav.profile'), href: '/profile' },
        ],
    };

    return map[role.value] || map.guest;
});

const shellTone = computed(() => {
    if (props.variant === 'admin') return 'from-[#0f6b5c]/10';
    if (props.variant === 'owner') return 'from-[#c45c26]/10';
    if (props.variant === 'user') return 'from-[#0f6b5c]/8';
    return 'from-transparent';
});
</script>

<template>
    <div class="min-h-screen overflow-x-hidden bg-wz-bg text-wz-fg">
        <Toast />
        <div class="pointer-events-none fixed inset-0 bg-gradient-to-b to-transparent" :class="shellTone" />

        <div class="relative mx-auto flex min-h-screen min-w-0 max-w-[1400px]">
            <!-- Desktop sidebar -->
            <aside
                v-if="variant !== 'guest'"
                class="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-e border-wz-border bg-wz-elevated/90 p-4 backdrop-blur md:flex"
            >
                <div class="mb-8 px-2">
                    <Link href="/" class="font-display text-xl font-bold tracking-tight text-wz-brand">
                        {{ t('brand.name') }}
                    </Link>
                    <p class="mt-1 text-xs text-wz-fg-muted">{{ t('brand.tagline') }}</p>
                </div>

                <nav class="flex flex-1 flex-col gap-1">
                    <Link
                        v-for="item in menus"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-xl px-3 py-2 text-sm text-wz-fg-muted transition hover:bg-wz-muted hover:text-wz-fg"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="mt-auto space-y-3 border-t border-wz-border pt-4">
                    <ThemeLocaleToggle />
                    <div v-if="user" class="px-1 text-xs text-wz-fg-muted">
                        {{ user.name }} · {{ t('common.role') }}: {{ role }}
                    </div>
                    <Link
                        v-if="user"
                        href="/logout"
                        method="post"
                        as="button"
                        class="wz-focus w-full rounded-xl bg-wz-danger px-3 py-2 text-sm text-white"
                    >
                        {{ t('nav.logout') }}
                    </Link>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 border-b border-wz-border bg-wz-bg/85 px-4 py-3 backdrop-blur sm:px-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <Button
                                v-if="variant !== 'guest'"
                                class="md:hidden"
                                variant="secondary"
                                size="sm"
                                type="button"
                                :aria-label="t('common.menu')"
                                :aria-expanded="mobileOpen"
                                aria-controls="mobile-nav"
                                @click="mobileOpen = !mobileOpen"
                            >
                                {{ t('common.menu') }}
                            </Button>
                            <div>
                                <p class="font-display text-sm font-semibold text-wz-brand md:hidden">
                                    {{ t('brand.name') }}
                                </p>
                                <h1 v-if="title" class="text-base font-semibold text-wz-fg sm:text-lg">
                                    {{ title }}
                                </h1>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <ThemeLocaleToggle class="hidden sm:flex" />
                            <span v-if="user" class="hidden text-sm text-wz-fg-muted lg:inline">
                                {{ user.name }}
                            </span>
                        </div>
                    </div>

                    <nav
                        v-if="mobileOpen && variant !== 'guest'"
                        id="mobile-nav"
                        class="mt-3 grid gap-1 rounded-xl border border-wz-border bg-wz-elevated p-2 md:hidden"
                    >
                        <Link
                            v-for="item in menus"
                            :key="item.href + '-m'"
                            :href="item.href"
                            class="rounded-lg px-3 py-2 text-sm hover:bg-wz-muted"
                            @click="mobileOpen = false"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </header>

                <main class="min-w-0 flex-1 overflow-x-hidden px-4 py-6 sm:px-6">
                    <slot />
                </main>

                <footer class="border-t border-wz-border px-4 py-4 text-center text-xs text-wz-fg-muted sm:px-6">
                    © {{ new Date().getFullYear() }} {{ t('brand.name') }} · {{ t('brand.tagline') }}
                </footer>
            </div>
        </div>
    </div>
</template>
