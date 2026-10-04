<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppShell from '@/Components/Ui/AppShell.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    status: { type: Number, required: true },
});

const { t } = useI18n();
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

const codeKey = computed(() => {
    const known = [403, 404, 419, 500, 503];
    return known.includes(props.status) ? String(props.status) : 'generic';
});

const title = computed(() => t(`errors.status.${codeKey.value}.title`));
const message = computed(() => t(`errors.status.${codeKey.value}.message`));
</script>

<template>
    <AppShell variant="guest" :title="title">
        <Head :title="title" />

        <div class="mx-auto flex max-w-lg flex-col items-center py-10 text-center sm:py-16">
            <p class="font-display text-6xl font-bold tabular-nums text-wz-brand sm:text-7xl">
                {{ status }}
            </p>
            <h2 class="mt-4 text-xl font-semibold text-wz-fg sm:text-2xl">
                {{ title }}
            </h2>
            <p class="mt-3 text-sm text-wz-fg-muted sm:text-base">
                {{ message }}
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
