<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/Ui/Button.vue';
import ThemeLocaleToggle from '@/Components/Ui/ThemeLocaleToggle.vue';
import Toast from '@/Components/Ui/Toast.vue';
import WorkspaceCard from '@/Components/Ui/WorkspaceCard.vue';

defineProps({
    featured: { type: Array, default: () => [] },
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('brand.name')" />
    <div class="min-h-screen bg-wz-bg text-wz-fg">
        <Toast />
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,var(--wz-brand-soft),transparent_35%),radial-gradient(circle_at_80%_0%,var(--wz-accent-soft),transparent_30%)]" />

        <header class="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
            <div>
                <p class="font-display text-xl font-bold text-wz-brand">{{ t('brand.name') }}</p>
                <p class="text-xs text-wz-fg-muted">{{ t('brand.tagline') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <ThemeLocaleToggle />
                <Link :href="route('login')" class="hidden sm:inline-flex">
                    <Button variant="secondary" size="sm">{{ t('nav.login') }}</Button>
                </Link>
            </div>
        </header>

        <main class="relative mx-auto max-w-6xl px-4 sm:px-6">
            <section class="grid items-center gap-10 py-10 lg:grid-cols-2 lg:py-16">
                <div>
                    <p class="mb-3 text-sm font-medium text-wz-accent">{{ t('home.eyebrow') }}</p>
                    <h1 class="font-display text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                        {{ t('home.title') }}
                    </h1>
                    <p class="mt-4 max-w-xl text-base text-wz-fg-muted sm:text-lg">
                        {{ t('home.subtitle') }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('spaces.index')">
                            <Button size="lg">{{ t('home.cta') }}</Button>
                        </Link>
                        <Link :href="route('register')">
                            <Button size="lg" variant="secondary">{{ t('nav.register') }}</Button>
                        </Link>
                    </div>
                </div>
                <div class="wz-surface overflow-hidden">
                    <img
                        src="https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=1200&auto=format&fit=crop"
                        alt="WorkZone"
                        class="min-h-[280px] w-full object-cover"
                    />
                </div>
            </section>

            <section class="py-10">
                <h2 class="mb-6 font-display text-2xl font-semibold">{{ t('home.howTitle') }}</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">01</div>
                        <h3 class="font-semibold">{{ t('home.how1') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how1Hint') }}</p>
                    </div>
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">02</div>
                        <h3 class="font-semibold">{{ t('home.how2') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how2Hint') }}</p>
                    </div>
                    <div class="wz-surface p-5">
                        <div class="mb-2 text-sm font-semibold text-wz-brand">03</div>
                        <h3 class="font-semibold">{{ t('home.how3') }}</h3>
                        <p class="mt-1 text-sm text-wz-fg-muted">{{ t('home.how3Hint') }}</p>
                    </div>
                </div>
            </section>

            <section v-if="featured.length" class="py-10">
                <div class="mb-6 flex items-end justify-between gap-3">
                    <h2 class="font-display text-2xl font-semibold">{{ t('home.featuredTitle') }}</h2>
                    <Link :href="route('spaces.index')" class="text-sm text-wz-brand hover:underline">
                        {{ t('home.cta') }}
                    </Link>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <WorkspaceCard v-for="space in featured" :key="space.id" :space="space" />
                </div>
            </section>

            <section class="wz-surface my-10 overflow-hidden bg-wz-brand-soft p-8 sm:p-10">
                <h2 class="font-display text-2xl font-semibold text-wz-fg">{{ t('home.ctaBand') }}</h2>
                <p class="mt-2 max-w-xl text-wz-fg-muted">{{ t('home.ctaBandHint') }}</p>
                <div class="mt-6">
                    <Link :href="route('spaces.index')">
                        <Button size="lg">{{ t('home.cta') }}</Button>
                    </Link>
                </div>
            </section>
        </main>

        <footer class="relative border-t border-wz-border py-6 text-center text-xs text-wz-fg-muted">
            © {{ new Date().getFullYear() }} {{ t('brand.name') }} · {{ t('brand.tagline') }}
        </footer>
    </div>
</template>
