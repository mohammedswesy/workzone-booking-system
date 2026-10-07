<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import StatCard from '@/Components/Ui/StatCard.vue';

const props = defineProps({
    balances: Object,
    openRequest: Object,
    payouts: Object,
    ledger: Object,
    payoutProfile: Object,
});

const { t } = useI18n();

const form = useForm({
    amount: props.balances?.available || '0',
    owner_note: '',
});

function statusTone(status) {
    const map = { requested: 'warning', approved: 'accent', paid: 'success', rejected: 'danger' };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="t('owner.payoutsTitle')">
        <Head :title="t('owner.payoutsTitle')" />
        <PageHeader :title="t('owner.payoutsTitle')" :subtitle="t('owner.payoutsHint')" />

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <StatCard :label="t('owner.availableBalance')" :value="balances.available" />
            <StatCard :label="t('owner.pendingBalance')" :value="balances.pending" />
            <StatCard :label="t('owner.ledgerBalance')" :value="balances.balance" />
        </div>

        <div class="mb-4 rounded-xl border border-wz-border bg-wz-muted/40 px-4 py-3 text-sm text-wz-fg-muted">
            <p>
                {{ t('owner.payoutProfileSummary') }}:
                {{ payoutProfile.payout_method || '—' }} ·
                {{ payoutProfile.payout_account_identifier || t('owner.payoutProfileMissing') }}
            </p>
            <Link :href="route('profile.edit')" class="text-wz-brand hover:underline">
                {{ t('owner.editPayoutProfile') }}
            </Link>
        </div>

        <form
            v-if="!openRequest"
            class="wz-surface mb-6 max-w-lg space-y-3 p-5"
            @submit.prevent="form.post(route('owner.payouts.store'))"
        >
            <h2 class="font-semibold">{{ t('owner.requestPayout') }}</h2>
            <Input id="amount" v-model="form.amount" type="number" step="0.01" :error="form.errors.amount">
                <template #label>{{ t('owner.payoutAmount') }}</template>
            </Input>
            <Button type="submit" variant="primary" :disabled="form.processing">{{ t('owner.requestPayout') }}</Button>
        </form>
        <div v-else class="wz-surface mb-6 p-5 text-sm">
            <p class="font-medium">{{ t('owner.openPayoutRequest') }}</p>
            <p class="mt-1 text-wz-fg-muted">
                #{{ openRequest.id }} · {{ openRequest.amount_requested }} ·
                <Badge :tone="statusTone(openRequest.status)">{{ openRequest.status }}</Badge>
            </p>
        </div>

        <div class="wz-surface mb-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">#</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.status') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payouts.data" :key="p.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ p.id }}</td>
                        <td class="px-3 py-2">{{ p.amount_approved || p.amount_requested }}</td>
                        <td class="px-3 py-2">
                            <Badge :tone="statusTone(p.status)">{{ p.status }}</Badge>
                        </td>
                        <td class="px-3 py-2">
                            <Link
                                v-if="p.status === 'paid'"
                                :href="route('owner.payouts.statement', p.id)"
                                class="text-wz-brand hover:underline"
                            >
                                {{ t('owner.viewStatement') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="payouts.links" />
        </div>

        <div class="wz-surface overflow-x-auto">
            <h2 class="border-b border-wz-border px-3 py-2 font-semibold">{{ t('owner.ledgerHistory') }}</h2>
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ t('owner.ledgerType') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in ledger.data" :key="e.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ e.type }}</td>
                        <td class="px-3 py-2">{{ e.amount }}</td>
                        <td class="px-3 py-2 text-wz-fg-muted">{{ e.note }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="ledger.links" />
        </div>
    </AppLayout>
</template>
