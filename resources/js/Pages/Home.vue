<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/Ui/Button.vue';
import ThemeLocaleToggle from '@/Components/Ui/ThemeLocaleToggle.vue';
import Toast from '@/Components/Ui/Toast.vue';

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

        <main class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:py-16">
            <div>
                <p class="mb-3 text-sm font-medium text-wz-accent">{{ t('home.eyebrow') }}</p>
                <h1 class="font-display text-4xl font-semibold leading-tight tracking-tight text-wz-fg sm:text-5xl">
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
                    alt="WorkZone coworking"
                    class="h-full min-h-[280px] w-full object-cover"
                />
            </div>
        </main>
    </div>
</template>
