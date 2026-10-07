<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    owner: Object,
    snapshot: Object,
    workspaceBreakdown: { type: Array, default: () => [] },
    filters: Object,
    generatedAt: String,
});

const { t, locale } = useI18n();

function money(value) {
    return `$ ${Number(value ?? 0).toFixed(2)}`;
}

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
    <AppLayout :title="t('admin.oaAccountSummary')">
        <Head :title="t('admin.oaAccountSummary')" />
        <PageHeader
            :title="t('admin.oaAccountSummary')"
            :subtitle="`${owner.name} · ${owner.email}`"
        >
            <template #actions>
                <Link :href="route('admin.owner-accounts.show', owner.id)">
                    <Button variant="secondary" class="print:hidden">{{ t('common.back') }}</Button>
                </Link>
                <Button variant="primary" type="button" class="print:hidden" @click="window.print()">
                    {{ t('owner.printStatement') }}
                </Button>
            </template>
        </PageHeader>

        <section class="wz-surface mb-4 grid gap-3 p-5 sm:grid-cols-2 print:border print:shadow-none">
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.from') }}</div>
                <div class="font-medium">{{ filters.from || '—' }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.to') }}</div>
                <div class="font-medium">{{ filters.to || '—' }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.oaGeneratedAt') }}</div>
                <div class="font-medium">{{ formatDate(generatedAt) }}</div>
            </div>
        </section>

        <section class="wz-surface mb-4 grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 print:border">
            <div>{{ t('admin.oaEarnings') }}: <strong>{{ money(snapshot.earnings) }}</strong></div>
            <div>{{ t('admin.oaCommission') }}: <strong>{{ money(snapshot.commission_taken) }}</strong></div>
            <div>{{ t('admin.oaRefunds') }}: <strong>{{ money(snapshot.refunds) }}</strong></div>
            <div>{{ t('admin.oaPaidOut') }}: <strong>{{ money(snapshot.paid_out) }}</strong></div>
            <div>{{ t('owner.availableBalance') }}: <strong>{{ money(snapshot.available) }}</strong></div>
            <div>{{ t('owner.pendingBalance') }}: <strong>{{ money(snapshot.pending) }}</strong></div>
            <div>{{ t('owner.ledgerBalance') }}: <strong>{{ money(snapshot.balance) }}</strong></div>
        </section>

        <div class="wz-surface overflow-x-auto print:border">
            <h2 class="border-b border-wz-border px-3 py-2 font-semibold">{{ t('admin.oaWorkspaceBreakdown') }}</h2>
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaBookingsCount') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaGross') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaCommission') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaRefunds') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaNet') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in workspaceBreakdown" :key="row.workspace_id ?? 'none'" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ row.workspace_name }}</td>
                        <td class="px-3 py-2">{{ row.bookings_count }}</td>
                        <td class="px-3 py-2">{{ money(row.gross) }}</td>
                        <td class="px-3 py-2">{{ money(row.commission) }}</td>
                        <td class="px-3 py-2">{{ money(row.refunds) }}</td>
                        <td class="px-3 py-2">{{ money(row.net) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
