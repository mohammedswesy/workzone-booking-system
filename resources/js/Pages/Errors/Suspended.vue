<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppShell from '@/Components/Ui/AppShell.vue';
import Button from '@/Components/Ui/Button.vue';

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <AppShell variant="guest" :title="t('errors.suspended.title')">
        <Head :title="t('errors.suspended.title')" />

        <div class="mx-auto flex max-w-lg flex-col items-center py-10 text-center sm:py-16">
            <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-wz-danger/15 text-wz-danger"
                aria-hidden="true"
            >
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636"
                    />
                </svg>
            </div>
            <h2 class="mt-6 text-xl font-semibold text-wz-fg sm:text-2xl">
                {{ t('errors.suspended.title') }}
            </h2>
            <p class="mt-3 text-sm text-wz-fg-muted sm:text-base">
                {{ t('errors.suspended.message') }}
            </p>
            <p class="mt-2 text-sm text-wz-fg">
                {{ t('errors.suspended.support') }}
            </p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Link :href="route('home')">
                    <Button>{{ t('errors.ctaHome') }}</Button>
                </Link>
                <Link v-if="!user" :href="route('login')">
                    <Button variant="secondary">{{ t('errors.ctaLogin') }}</Button>
                </Link>
            </div>
        </div>
    </AppShell>
</template>
