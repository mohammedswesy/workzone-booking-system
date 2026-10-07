<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Badge from '@/Components/Ui/Badge.vue';
import StatCard from '@/Components/Ui/StatCard.vue';

const props = defineProps({
    payout: Object,
    balances: Object,
});

const { t } = useI18n();

const approveForm = useForm({
    amount_approved: props.payout.amount_requested,
    admin_note: '',
});

const payForm = useForm({
    payout_method: props.payout.payout_method || 'bank_transfer',
    transfer_reference: '',
    paid_at: new Date().toISOString().slice(0, 10),
    admin_note: '',
});

const rejectForm = useForm({
    rejection_reason: '',
});

function statusTone(status) {
    const map = { requested: 'warning', approved: 'accent', paid: 'success', rejected: 'danger' };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="`${t('admin.payoutDetails')} #${payout.id}`">
        <Head :title="`${t('admin.payoutDetails')} #${payout.id}`" />
        <PageHeader :title="`${t('admin.payoutDetails')} #${payout.id}`" :subtitle="payout.owner?.name">
            <template #actions>
                <Link :href="route('admin.payouts.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
                <Link
                    v-if="payout.status === 'paid'"
                    :href="route('admin.payouts.statement', payout.id)"
                >
                    <Button variant="secondary">{{ t('owner.viewStatement') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <StatCard :label="t('owner.availableBalance')" :value="balances.available" />
            <StatCard :label="t('owner.pendingBalance')" :value="balances.pending" />
            <StatCard :label="t('owner.ledgerBalance')" :value="balances.balance" />
        </div>

        <section class="wz-surface mb-4 grid gap-3 p-5 sm:grid-cols-2">
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('common.status') }}</div>
                <Badge class="mt-1" :tone="statusTone(payout.status)">{{ payout.status }}</Badge>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('owner.payoutAmount') }}</div>
                <div class="mt-1 font-semibold">{{ payout.amount_approved || payout.amount_requested }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('owner.payoutProfileSummary') }}</div>
                <div class="mt-1 text-sm">
                    {{ payout.payout_method }} · {{ payout.payout_account_holder }} ·
                    {{ payout.payout_account_identifier }}
                </div>
            </div>
            <div v-if="payout.owner_note">
                <div class="text-sm text-wz-fg-muted">{{ t('owner.ownerNote') }}</div>
                <div class="mt-1 text-sm">{{ payout.owner_note }}</div>
            </div>
            <div v-if="payout.rejection_reason">
                <div class="text-sm text-wz-fg-muted">{{ t('admin.rejectionReason') }}</div>
                <div class="mt-1 text-sm text-wz-danger">{{ payout.rejection_reason }}</div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <form
                v-if="payout.status === 'requested'"
                class="wz-surface space-y-3 p-5"
                @submit.prevent="approveForm.post(route('admin.payouts.approve', payout.id))"
            >
                <h2 class="font-semibold">{{ t('admin.approvePayout') }}</h2>
                <Input id="amount_approved" v-model="approveForm.amount_approved" type="number" step="0.01">
                    <template #label>{{ t('admin.amountApproved') }}</template>
                </Input>
                <Input id="approve_note" v-model="approveForm.admin_note">
                    <template #label>{{ t('admin.adminNote') }}</template>
                </Input>
                <Button type="submit" variant="primary" :disabled="approveForm.processing">
                    {{ t('admin.approvePayout') }}
                </Button>
            </form>

            <form
                v-if="['requested', 'approved'].includes(payout.status)"
                class="wz-surface space-y-3 p-5"
                @submit.prevent="payForm.post(route('admin.payouts.pay', payout.id))"
            >
                <h2 class="font-semibold">{{ t('admin.markPayoutPaid') }}</h2>
                <Select id="pay_method" v-model="payForm.payout_method">
                    <template #label>{{ t('payment.method') }}</template>
                    <option value="jawwal_pay">{{ t('payment.methodJawwal') }}</option>
                    <option value="bank_transfer">{{ t('payment.methodBank') }}</option>
                    <option value="other_wallet">{{ t('payment.methodOtherWallet') }}</option>
                    <option value="cash">{{ t('payment.methodCash') }}</option>
                </Select>
                <Input id="transfer_reference" v-model="payForm.transfer_reference" :error="payForm.errors.transfer_reference">
                    <template #label>{{ t('payment.transferReference') }}</template>
                </Input>
                <Input id="paid_at" v-model="payForm.paid_at" type="date">
                    <template #label>{{ t('admin.paidAt') }}</template>
                </Input>
                <Button type="submit" variant="primary" :disabled="payForm.processing">
                    {{ t('admin.markPayoutPaid') }}
                </Button>
            </form>

            <form
                v-if="['requested', 'approved'].includes(payout.status)"
                class="wz-surface space-y-3 p-5"
                @submit.prevent="rejectForm.post(route('admin.payouts.reject', payout.id))"
            >
                <h2 class="font-semibold">{{ t('admin.rejectPayout') }}</h2>
                <Input id="rejection_reason" v-model="rejectForm.rejection_reason" :error="rejectForm.errors.rejection_reason">
                    <template #label>{{ t('admin.rejectionReason') }}</template>
                </Input>
                <Button type="submit" variant="danger" :disabled="rejectForm.processing">
                    {{ t('admin.rejectPayout') }}
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
