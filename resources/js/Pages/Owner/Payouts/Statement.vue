<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    payout: Object,
    entries: { type: Array, default: () => [] },
});

const { t, locale } = useI18n();

const isAdmin = computed(() => route().current()?.startsWith('admin.'));

const csvRoute = computed(() => {
    const base = isAdmin.value
        ? route('admin.payouts.statement.csv', props.payout.id)
        : route('owner.payouts.statement.csv', props.payout.id);
    const localeParam = locale.value === 'ar' ? 'ar' : 'en';
    return `${base}?locale=${localeParam}`;
});

const backRoute = computed(() =>
    isAdmin.value ? route('admin.payouts.show', props.payout.id) : route('owner.payouts.index'),
);

const totals = computed(() => {
    const sum = (type) =>
        props.entries
            .filter((e) => e.type === type)
            .reduce((acc, e) => acc + Number(e.amount || 0), 0);
    return {
        earnings: sum('earning').toFixed(2),
        commissions: sum('commission').toFixed(2),
        refunds: sum('refund').toFixed(2),
        adjustments: sum('adjustment').toFixed(2),
        payout: sum('payout').toFixed(2),
        net: props.entries.reduce((acc, e) => acc + Number(e.amount || 0), 0).toFixed(2),
    };
});

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
</script>

<template>
    <AppLayout :title="t('owner.statementTitle')">
        <Head :title="t('owner.statementTitle')" />
        <PageHeader
            :title="t('owner.statementTitle')"
            :subtitle="`${payout.owner?.name || ''} · #${payout.id}`"
        >
            <template #actions>
                <Link :href="backRoute">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
                <a :href="csvRoute">
                    <Button variant="primary">{{ t('admin.exportCsv') }}</Button>
                </a>
                <Button variant="secondary" type="button" class="print:hidden" @click="window.print()">
                    {{ t('owner.printStatement') }}
                </Button>
            </template>
        </PageHeader>

        <section class="wz-surface mb-4 grid gap-3 p-5 sm:grid-cols-2 print:border print:shadow-none">
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.owner') }}</div>
                <div class="font-medium">{{ payout.owner?.name }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('payment.transferReference') }}</div>
                <div class="font-medium">{{ payout.transfer_reference || '—' }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.paidAt') }}</div>
                <div class="font-medium">{{ formatDate(payout.paid_at) }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('owner.payoutAmount') }}</div>
                <div class="font-medium">{{ payout.amount_approved || payout.amount_requested }}</div>
            </div>
        </section>

        <div class="wz-surface overflow-x-auto print:border">
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ t('owner.ledgerType') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.date') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.adminNote') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in entries" :key="e.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ e.type }}</td>
                        <td class="px-3 py-2">{{ e.booking?.workspace?.name || '—' }}</td>
                        <td class="px-3 py-2">{{ formatDate(e.created_at) }}</td>
                        <td class="px-3 py-2">{{ e.amount }}</td>
                        <td class="px-3 py-2 text-wz-fg-muted">{{ e.note }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>{{ t('owner.earningsTotal') }}: {{ totals.earnings }}</div>
            <div>{{ t('owner.commissionTotal') }}: {{ totals.commissions }}</div>
            <div>{{ t('owner.refundsTotal') }}: {{ totals.refunds }}</div>
            <div>{{ t('owner.adjustmentsTotal') }}: {{ totals.adjustments }}</div>
            <div>{{ t('owner.netPayout') }}: {{ payout.amount_approved || payout.amount_requested }}</div>
        </div>
    </AppLayout>
</template>
