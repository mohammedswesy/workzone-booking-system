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
            workspaces_count: 0,
            bookings_count: 0,
            pending_count: 0,
            active_offers_count: 0,
            revenue: '0',
        }),
    },
    topWorkspaces: { type: Array, default: () => [] },
    recentBookings: { type: Array, default: () => [] },
    activeOffers: { type: Array, default: () => [] },
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
    <AppLayout :title="t('owner.dashboardTitle')">
        <Head :title="t('owner.dashboardTitle')" />

        <PageHeader
            :title="t('owner.dashboardTitle')"
            :subtitle="t('owner.dashboardSubtitle')"
        >
            <template #actions>
                <Link :href="route('owner.workspaces.index')">
                    <Button variant="secondary">{{ t('owner.manageSpaces') }}</Button>
                </Link>
                <Link :href="route('owner.offers.create')">
                    <Button variant="primary">{{ t('owner.addOffer') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard :label="t('owner.statSpaces')" :value="stats?.workspaces_count ?? 0" />
            <StatCard :label="t('owner.statBookings')" :value="stats?.bookings_count ?? 0" />
            <StatCard :label="t('owner.statPending')" :value="stats?.pending_count ?? 0" />
            <StatCard :label="t('owner.statOffers')" :value="stats?.active_offers_count ?? 0" />
            <StatCard
                :label="t('owner.statRevenue')"
                :value="`$ ${Number(stats?.revenue ?? 0).toFixed(2)}`"
            />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-semibold text-wz-fg">
                        {{ t('owner.recentBookings') }}
                    </h2>
                    <Link
                        :href="route('owner.bookings.index')"
                        class="text-sm font-medium text-wz-brand hover:opacity-90"
                    >
                        {{ t('owner.allBookings') }}
                    </Link>
                </div>

                <EmptyState
                    v-if="!recentBookings?.length"
                    :title="t('owner.noBookings')"
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
                                <span class="truncate font-medium text-wz-fg">
                                    {{ b.workspace?.name }}
                                </span>
                                <Badge :tone="statusTone(b.status)">
                                    {{ t(`status.${b.status}`, b.status) }}
                                </Badge>
                            </div>
                            <p class="mt-1 text-sm text-wz-fg-muted">
                                {{ b.user?.name }} · {{ formatDate(b.start_at || b.created_at) }}
                            </p>
                        </div>
                        <Link :href="route('owner.bookings.show', b.id)">
                            <Button size="sm" variant="secondary">{{ t('bookings.view') }}</Button>
                        </Link>
                    </li>
                </ul>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="font-display text-lg font-semibold text-wz-fg">
                        {{ t('owner.activeOffers') }}
                    </h2>
                    <Link
                        :href="route('owner.offers.index')"
                        class="text-sm font-medium text-wz-brand hover:opacity-90"
                    >
                        {{ t('owner.offersTitle') }}
                    </Link>
                </div>

                <EmptyState
                    v-if="!activeOffers?.length"
                    :title="t('owner.noOffers')"
                    :description="t('common.emptyHint')"
                >
                    <template #action>
                        <Link :href="route('owner.offers.create')">
                            <Button size="sm" variant="primary">{{ t('owner.addOffer') }}</Button>
                        </Link>
                    </template>
                </EmptyState>
                <ul v-else class="wz-surface divide-y divide-wz-border overflow-hidden">
                    <li
                        v-for="o in activeOffers"
                        :key="o.id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium text-wz-fg">{{ o.title }}</div>
                            <p class="text-sm text-wz-fg-muted">
                                {{ o.workspace?.name }} · {{ o.discount_percent }}%
                            </p>
                        </div>
                        <Badge tone="success">{{ t('owner.isActive') }}</Badge>
                    </li>
                </ul>
            </section>
        </div>

        <section class="mt-6">
            <h2 class="mb-3 font-display text-lg font-semibold text-wz-fg">
                {{ t('owner.topSpaces') }}
            </h2>
            <EmptyState
                v-if="!topWorkspaces?.length"
                :title="t('owner.noBookings')"
                :description="t('common.emptyHint')"
            />
            <ul v-else class="wz-surface divide-y divide-wz-border overflow-hidden">
                <li
                    v-for="w in topWorkspaces"
                    :key="w.id"
                    class="flex items-center justify-between gap-3 px-4 py-3"
                >
                    <div>
                        <div class="font-medium text-wz-fg">{{ w.name }}</div>
                        <div class="text-sm text-wz-fg-muted">
                            {{ w.bookings_count }} {{ t('nav.bookings').toLowerCase() }}
                        </div>
                    </div>
                    <div class="font-semibold text-wz-fg">
                        $ {{ Number(w.revenue || 0).toFixed(2) }}
                    </div>
                </li>
            </ul>
        </section>
    </AppLayout>
</template>
