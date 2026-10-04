<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';

defineProps({
    stats: Object,
    upcoming: Array,
});

const { t, locale } = useI18n();

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString(locale.value === 'ar' ? 'ar' : 'en', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    } catch {
        return value;
    }
}

function statusTone(status) {
    const map = {
        pending: 'warning',
        confirmed: 'success',
        cancelled: 'danger',
        completed: 'brand',
        no_show: 'neutral',
    };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="t('bookings.dashboardTitle')">
        <Head :title="t('bookings.dashboardTitle')" />

        <PageHeader
            :title="t('bookings.dashboardTitle')"
            :subtitle="t('bookings.dashboardSubtitle')"
        >
            <template #actions>
                <Link :href="route('spaces.index')">
                    <Button variant="primary">{{ t('bookings.browseSpaces') }}</Button>
                </Link>
                <Link :href="route('user.bookings.index')">
                    <Button variant="secondary">{{ t('bookings.title') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard :label="t('bookings.statAll')" :value="stats.bookings_count ?? 0" />
            <StatCard :label="t('bookings.statPending')" :value="stats.pending_count ?? 0" />
            <StatCard :label="t('bookings.statConfirmed')" :value="stats.confirmed_count ?? 0" />
            <StatCard :label="t('bookings.statUnpaid')" :value="stats.unpaid_count ?? 0" />
        </div>

        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('bookings.upcoming') }}
                </h2>
                <Link
                    :href="route('user.bookings.index')"
                    class="text-sm font-medium text-wz-brand hover:opacity-90"
                >
                    {{ t('bookings.title') }}
                </Link>
            </div>

            <EmptyState
                v-if="!upcoming?.length"
                :title="t('bookings.noUpcoming')"
                :description="t('bookings.emptyHint')"
            >
                <template #action>
                    <Link :href="route('spaces.index')">
                        <Button variant="primary" size="sm">{{ t('bookings.browseSpaces') }}</Button>
                    </Link>
                </template>
            </EmptyState>

            <ul v-else class="wz-surface divide-y divide-wz-border overflow-hidden">
                <li
                    v-for="b in upcoming"
                    :key="b.id"
                    class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-medium text-wz-fg">
                                {{ b.workspace?.name }}
                            </span>
                            <Badge :tone="statusTone(b.status)">
                                {{ t(`status.${b.status}`, b.status) }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-wz-fg-muted">
                            {{ formatDate(b.start_at) }}
                        </p>
                    </div>
                    <Link :href="route('user.bookings.show', b.id)">
                        <Button variant="secondary" size="sm">{{ t('bookings.view') }}</Button>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
