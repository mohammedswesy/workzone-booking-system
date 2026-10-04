<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ThemeLocaleToggle from '@/Components/Ui/ThemeLocaleToggle.vue';
import Toast from '@/Components/Ui/Toast.vue';

const { t } = useI18n();
</script>

<template>
    <div class="relative min-h-screen bg-wz-bg text-wz-fg">
        <Toast />
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,var(--wz-brand-soft),transparent_40%),radial-gradient(circle_at_bottom_right,var(--wz-accent-soft),transparent_35%)]" />

        <div class="relative mx-auto flex min-h-screen max-w-lg flex-col px-4 py-8">
            <div class="mb-8 flex items-center justify-between">
                <Link href="/" class="font-display text-xl font-bold text-wz-brand">
                    {{ t('brand.name') }}
                </Link>
                <ThemeLocaleToggle />
            </div>

            <div class="wz-surface flex-1 px-6 py-6 sm:px-8">
                <p class="mb-1 text-xs font-medium uppercase tracking-[0.14em] text-wz-accent">
                    {{ t('brand.tagline') }}
                </p>
                <slot />
            </div>

            <p class="mt-6 text-center text-xs text-wz-fg-muted">
                © {{ new Date().getFullYear() }} {{ t('brand.name') }}
            </p>
        </div>
    </div>
</template>
