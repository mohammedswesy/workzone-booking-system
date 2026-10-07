<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Badge from '@/Components/Ui/Badge.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

const props = defineProps({
    owner: Object,
    snapshot: Object,
    openPayout: Object,
    workspaceBreakdown: { type: Array, default: () => [] },
    ledger: Object,
    payouts: Object,
    workspaces: { type: Array, default: () => [] },
    filters: Object,
});

const { t, locale } = useI18n();

const form = reactive({
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    workspace_id: props.filters?.workspace_id || '',
});

function apply() {
    router.get(route('admin.owner-accounts.show', props.owner.id), form, {
        preserveState: true,
        replace: true,
    });
}

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

function statusTone(status) {
    const map = { requested: 'warning', approved: 'accent', paid: 'success', rejected: 'danger' };
    return map[status] || 'neutral';
}

const summaryUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });
    const q = params.toString();
    return `${route('admin.owner-accounts.summary', props.owner.id)}${q ? `?${q}` : ''}`;
});

const csvUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });
    params.set('locale', locale.value === 'ar' ? 'ar' : 'en');
    return `${route('admin.owner-accounts.export', props.owner.id)}?${params.toString()}`;
});
</script>

<template>
    <AppLayout :title="`${t('admin.ownerAccountDetail')} · ${owner.name}`">
        <Head :title="`${t('admin.ownerAccountDetail')} · ${owner.name}`" />
        <PageHeader :title="owner.name" :subtitle="owner.email">
            <template #actions>
                <Link :href="route('admin.owner-accounts.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
                <a :href="summaryUrl" target="_blank" rel="noopener">
                    <Button variant="secondary">{{ t('admin.oaPrintSummary') }}</Button>
                </a>
                <a :href="csvUrl">
                    <Button variant="primary">{{ t('admin.exportCsv') }}</Button>
                </a>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            <StatCard :label="t('admin.oaEarnings')" :value="money(snapshot.earnings)" />
            <StatCard :label="t('admin.oaCommission')" :value="money(snapshot.commission_taken)" />
            <StatCard :label="t('admin.oaRefunds')" :value="money(snapshot.refunds)" />
            <StatCard :label="t('admin.oaPaidOut')" :value="money(snapshot.paid_out)" />
            <StatCard :label="t('owner.availableBalance')" :value="money(snapshot.available)" />
            <StatCard :label="t('owner.pendingBalance')" :value="money(snapshot.pending)" />
            <StatCard :label="t('owner.ledgerBalance')" :value="money(snapshot.balance)" />
        </div>

        <div
            v-if="openPayout"
            class="wz-surface mb-4 flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
        >
            <div>
                <span class="font-medium">{{ t('admin.oaOpenPayout') }}:</span>
                #{{ openPayout.id }} · {{ money(openPayout.amount_approved || openPayout.amount_requested) }}
                <Badge class="ms-2" :tone="statusTone(openPayout.status)">{{ openPayout.status }}</Badge>
            </div>
            <Link :href="route('admin.payouts.show', openPayout.id)" class="text-wz-brand hover:underline">
                {{ t('common.view') }}
            </Link>
        </div>

        <form class="wz-surface mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="apply">
            <Input id="from" v-model="form.from" type="date">
                <template #label>{{ t('admin.from') }}</template>
            </Input>
            <Input id="to" v-model="form.to" type="date">
                <template #label>{{ t('admin.to') }}</template>
            </Input>
            <Select id="workspace_id" v-model="form.workspace_id">
                <template #label>{{ t('bookings.workspace') }}</template>
                <option value="">{{ t('common.all') }}</option>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
            </Select>
            <div class="flex items-end">
                <Button type="submit" variant="secondary" class="w-full">{{ t('common.apply') }}</Button>
            </div>
        </form>

        <section class="mb-6">
            <h2 class="mb-3 font-display text-lg font-semibold">{{ t('admin.oaWorkspaceBreakdown') }}</h2>
            <div class="wz-surface overflow-x-auto">
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
                        <tr v-if="!workspaceBreakdown.length">
                            <td colspan="6" class="px-3 py-4 text-wz-fg-muted">{{ t('common.empty') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mb-6">
            <h2 class="mb-3 font-display text-lg font-semibold">{{ t('owner.ledgerHistory') }}</h2>
            <div class="wz-surface overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">{{ t('common.date') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.ledgerType') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('admin.adminNote') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in ledger.data" :key="e.id" class="border-t border-wz-border">
                            <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(e.created_at) }}</td>
                            <td class="px-3 py-2">{{ e.type }}</td>
                            <td class="px-3 py-2">{{ e.booking?.workspace?.name || '—' }}</td>
                            <td class="px-3 py-2">{{ money(e.amount) }}</td>
                            <td class="px-3 py-2 text-wz-fg-muted">{{ e.note || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :links="ledger.links" />
            </div>
        </section>

        <section>
            <h2 class="mb-3 font-display text-lg font-semibold">{{ t('admin.oaPayoutHistory') }}</h2>
            <div class="wz-surface overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.status') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.date') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payouts.data" :key="p.id" class="border-t border-wz-border">
                            <td class="px-3 py-2">{{ p.id }}</td>
                            <td class="px-3 py-2">{{ money(p.amount_approved || p.amount_requested) }}</td>
                            <td class="px-3 py-2">
                                <Badge :tone="statusTone(p.status)">{{ p.status }}</Badge>
                            </td>
                            <td class="px-3 py-2">{{ formatDate(p.paid_at || p.created_at) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="route('admin.payouts.show', p.id)" class="text-wz-brand hover:underline">
                                    {{ t('common.view') }}
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :links="payouts.links" />
            </div>
        </section>
    </AppLayout>
</template>
