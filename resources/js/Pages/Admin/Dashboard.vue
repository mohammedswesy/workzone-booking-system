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
    stats: {
        type: Object,
        default: () => ({
            users: 0,
            owners: 0,
            workspaces: 0,
            bookings: 0,
            pending_bookings: 0,
            revenue: '0',
        }),
    },
    bookingsByStatus: { type: Object, default: () => ({}) },
    recentBookings: { type: Array, default: () => [] },
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
    <AppLayout :title="t('admin.dashboardTitle')">
        <Head :title="t('admin.dashboardTitle')" />

        <PageHeader
            :title="t('admin.dashboardTitle')"
            :subtitle="t('admin.dashboardSubtitle')"
        >
            <template #actions>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('admin.manageUsers') }}</Button>
                </Link>
                <Link :href="route('admin.workspaces.index')">
                    <Button variant="secondary">{{ t('admin.manageSpaces') }}</Button>
                </Link>
                <Link :href="route('admin.bookings.index')">
                    <Button variant="secondary">{{ t('admin.manageBookings') }}</Button>
                </Link>
                <Link :href="route('admin.reports.index')">
                    <Button variant="primary">{{ t('admin.viewReports') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <StatCard :label="t('admin.statUsers')" :value="stats.users ?? 0" />
            <StatCard :label="t('admin.statOwners')" :value="stats.owners ?? 0" />
            <StatCard :label="t('admin.statSpaces')" :value="stats.workspaces ?? 0" />
            <StatCard :label="t('admin.statBookings')" :value="stats.bookings ?? 0" />
            <StatCard :label="t('admin.statPending')" :value="stats.pending_bookings ?? 0" />
            <StatCard
                :label="t('admin.revenue')"
                :value="`$ ${Number(stats.revenue ?? 0).toFixed(2)}`"
            />
        </div>

        <section class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('admin.recentBookings') }}
                </h2>
                <Link
                    :href="route('admin.bookings.index')"
                    class="text-sm font-medium text-wz-brand hover:opacity-90"
                >
                    {{ t('admin.manageBookings') }}
                </Link>
            </div>

            <EmptyState
                v-if="!recentBookings?.length"
                :title="t('admin.noData')"
                :description="t('common.emptyHint')"
            />
            <ul v-else class="wz-surface divide-y divide-wz-border overflow-hidden">
                <li
                    v-for="b in recentBookings"
                    :key="b.id"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-wz-fg">
                                #{{ b.id }} — {{ b.workspace?.name ?? '—' }}
                            </span>
                            <Badge :tone="statusTone(b.status)">
                                {{ t(`status.${b.status}`, b.status) }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-wz-fg-muted">
                            {{ b.user?.name ?? '—' }} · $
                            {{ Number(b.total_price ?? 0).toFixed(2) }} ·
                            {{ formatDate(b.created_at) }}
                        </p>
                    </div>
                    <Link :href="route('admin.bookings.show', b.id)">
                        <Button size="sm" variant="secondary">{{ t('bookings.view') }}</Button>
                    </Link>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
