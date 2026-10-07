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
    platformMethodsMissing: { type: Boolean, default: false },
    demoImagesInUse: { type: Boolean, default: false },
    oldestPendingProofs: { type: Array, default: () => [] },
    reconciliation: { type: Object, default: null },
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
                <Link :href="route('admin.venues.index')">
                    <Button variant="secondary">{{ t('admin.manageSpaces') }}</Button>
                </Link>
                <Link :href="route('admin.bookings.index')">
                    <Button variant="secondary">{{ t('admin.manageBookings') }}</Button>
                </Link>
                <Link :href="route('admin.platform-payments.index')">
                    <Button variant="secondary">{{ t('nav.platformPayments') }}</Button>
                </Link>
                <Link :href="route('admin.payouts.index')">
                    <Button variant="secondary">{{ t('nav.payouts') }}</Button>
                </Link>
                <Link :href="route('admin.reports.index')">
                    <Button variant="primary">{{ t('admin.viewReports') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="platformMethodsMissing"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="alert"
        >
            <p class="font-semibold">{{ t('admin.platformMethodsMissingTitle') }}</p>
            <p class="mt-1 text-wz-fg-muted">{{ t('admin.platformMethodsMissingHint') }}</p>
            <Link
                :href="route('admin.platform-payments.index')"
                class="mt-2 inline-flex font-medium text-wz-brand hover:underline"
            >
                {{ t('admin.platformMethodsMissingCta') }}
            </Link>
        </div>

        <div
            v-if="demoImagesInUse"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="status"
            data-testid="demo-images-notice"
        >
            <p class="font-semibold">{{ t('admin.demoImagesInUseTitle') }}</p>
            <p class="mt-1 text-wz-fg-muted">{{ t('admin.demoImagesInUseHint') }}</p>
        </div>

        <div
            v-if="$page.props.flash?.warning"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="alert"
        >
            {{ $page.props.flash.warning }}
        </div>

        <section
            v-if="reconciliation"
            class="mb-6 rounded-xl border px-4 py-3 text-sm"
            :class="
                reconciliation.ok
                    ? 'border-wz-border bg-wz-elevated text-wz-fg'
                    : 'border-wz-danger/40 bg-red-50 text-wz-fg'
            "
            role="status"
        >
            <p class="font-semibold">{{ t('admin.reconcileTitle') }}</p>
            <p class="mt-1" :class="reconciliation.ok ? 'text-wz-fg-muted' : 'text-wz-danger'">
                {{
                    reconciliation.ok
                        ? t('admin.reconcileOk')
                        : t('admin.reconcileFail', { count: reconciliation.issue_count ?? 0 })
                }}
            </p>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <StatCard :label="t('admin.statUsers')" :value="stats.users ?? 0" />
            <StatCard :label="t('admin.statOwners')" :value="stats.owners ?? 0" />
            <StatCard :label="t('admin.statSpaces')" :value="stats.workspaces ?? 0" />
            <StatCard :label="t('admin.statBookings')" :value="stats.bookings ?? 0" />
            <StatCard :label="t('admin.statPending')" :value="stats.pending_bookings ?? 0" />
            <StatCard
                :label="t('admin.statCollected')"
                :value="`$ ${Number(stats.collected ?? stats.revenue ?? 0).toFixed(2)}`"
            />
            <StatCard
                :label="t('admin.statOwed')"
                :value="`$ ${Number(stats.owed_to_owners ?? 0).toFixed(2)}`"
            />
            <StatCard
                :label="t('admin.statCommission')"
                :value="`$ ${Number(stats.commission_earned ?? 0).toFixed(2)}`"
            />
            <StatCard
                :label="t('admin.statPaidOut')"
                :value="`$ ${Number(stats.paid_out ?? 0).toFixed(2)}`"
            />
            <StatCard
                :label="t('admin.statPendingProofs')"
                :value="stats.unpaid_pending_proofs ?? 0"
            />
            <StatCard :label="t('admin.statOpenPayouts')" :value="stats.open_payouts ?? 0" />
            <Link
                :href="route('admin.venues.index', { without_coordinates: 1 })"
                class="block"
                data-testid="venues-without-coords-card"
            >
                <StatCard
                    :label="t('admin.statVenuesWithoutCoords')"
                    :value="stats.venues_without_coordinates ?? 0"
                />
            </Link>
        </div>

        <section v-if="oldestPendingProofs?.length" class="mt-6">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('admin.oldestPendingProofs') }}
                </h2>
                <span class="text-sm text-wz-fg-muted">{{ t('admin.oldestPendingProofsHint') }}</span>
            </div>
            <div class="wz-surface overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.customer') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.total') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.date') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in oldestPendingProofs"
                            :key="p.id"
                            class="border-t border-wz-border"
                        >
                            <td class="px-3 py-2">{{ p.id }}</td>
                            <td class="px-3 py-2">{{ p.booking?.workspace?.name || '—' }}</td>
                            <td class="px-3 py-2">{{ p.booking?.user?.name || '—' }}</td>
                            <td class="px-3 py-2">$ {{ Number(p.amount ?? 0).toFixed(2) }}</td>
                            <td class="px-3 py-2">{{ formatDate(p.created_at) }}</td>
                            <td class="px-3 py-2">
                                <Link
                                    v-if="p.booking_id"
                                    :href="route('admin.bookings.show', p.booking_id)"
                                    class="text-wz-brand hover:underline"
                                >
                                    {{ t('common.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
