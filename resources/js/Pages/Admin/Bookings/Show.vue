<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({ booking: Object });

const { t } = useI18n();
const showCancel = ref(false);

const pendingPayment = computed(
    () => (props.booking.payments || []).find((p) => p.status === 'pending') || null,
);

const canConfirmBooking = computed(
    () => props.booking.status === 'pending' && props.booking.payment_status === 'paid',
);

const proofReuseWarning = computed(
    () => pendingPayment.value?.metadata?.proof_reuse_warning || null,
);

const confirmForm = useForm({
    received_amount: '',
    amount_disposition: '',
    amount_note: '',
});

const rejectForm = useForm({ reason: '' });

watch(
    pendingPayment,
    (payment) => {
        if (!payment) return;
        confirmForm.received_amount = payment.amount ?? props.booking.total_price ?? '';
        confirmForm.amount_disposition = '';
        confirmForm.amount_note = '';
    },
    { immediate: true },
);

function setStatus(status) {
    if (status === 'cancelled') {
        showCancel.value = true;
        return;
    }
    router.put(route('admin.bookings.update', props.booking.id), { status }, { preserveScroll: true });
}

function confirmCancel() {
    router.put(
        route('admin.bookings.update', props.booking.id),
        { status: 'cancelled' },
        {
            preserveScroll: true,
            onFinish: () => {
                showCancel.value = false;
            },
        },
    );
}

function confirmPayment() {
    if (!pendingPayment.value) return;
    confirmForm.post(route('payments.manual.confirm', pendingPayment.value.id), {
        preserveScroll: true,
    });
}

function rejectPayment() {
    if (!pendingPayment.value) return;
    rejectForm.post(route('payments.manual.reject', pendingPayment.value.id), { preserveScroll: true });
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

function paymentTone(status) {
    const map = {
        unpaid: 'warning',
        pending: 'accent',
        paid: 'success',
        failed: 'danger',
        refunded: 'neutral',
    };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="`${t('admin.bookingDetails')} #${booking.id}`">
        <Head :title="`${t('admin.bookingDetails')} #${booking.id}`" />

        <PageHeader
            :title="`${t('admin.bookingDetails')} #${booking.id}`"
            :subtitle="booking.workspace?.name || ''"
        >
            <template #actions>
                <Link :href="route('admin.bookings.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <section class="wz-surface grid gap-4 p-5 sm:grid-cols-2">
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('bookings.workspace') }}</div>
                <div class="mt-1 font-medium text-wz-fg">{{ booking.workspace?.name }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('owner.customer') }}</div>
                <div class="mt-1 font-medium text-wz-fg">{{ booking.user?.name }}</div>
                <div class="text-sm text-wz-fg-muted">{{ booking.user?.email }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('bookings.hours') }}</div>
                <div class="mt-1 font-medium text-wz-fg">{{ booking.hours }}</div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('bookings.total') }}</div>
                <div class="mt-1 font-medium text-wz-fg">
                    $ {{ Number(booking.total_price ?? 0).toFixed(2) }}
                </div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('bookings.status') }}</div>
                <div class="mt-1">
                    <Badge :tone="statusTone(booking.status)">
                        {{ t(`status.${booking.status}`, booking.status) }}
                    </Badge>
                </div>
            </div>
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('bookings.paymentStatus') }}</div>
                <div class="mt-1">
                    <Badge :tone="paymentTone(booking.payment_status)">
                        {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                    </Badge>
                </div>
            </div>
        </section>

        <section v-if="pendingPayment" class="wz-surface mt-4 space-y-4 p-5">
            <h2 class="font-semibold">{{ t('payment.reviewProof') }}</h2>

            <div
                v-if="proofReuseWarning"
                class="rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
                role="alert"
            >
                <p class="font-medium">{{ t('payment.proofReuseWarning') }}</p>
                <p class="mt-1 text-wz-fg-muted">{{ proofReuseWarning }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-sm text-wz-fg-muted">{{ t('payment.expectedAmount') }}</div>
                    <div class="mt-1 font-medium text-wz-fg">
                        $ {{ Number(pendingPayment.amount ?? booking.total_price ?? 0).toFixed(2) }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-wz-fg-muted">{{ t('payment.transferReference') }}</div>
                    <div class="mt-1 font-medium text-wz-fg">
                        {{ pendingPayment.transfer_reference || '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-sm text-wz-fg-muted">{{ t('payment.viewProof') }}</div>
                    <a
                        v-if="pendingPayment.proof_url"
                        :href="pendingPayment.proof_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-1 inline-flex text-sm font-medium text-wz-brand hover:underline"
                    >
                        {{ t('payment.viewProof') }}
                    </a>
                    <div v-else class="mt-1 text-sm text-wz-fg-muted">{{ t('payment.noProof') }}</div>
                </div>
            </div>

            <p class="text-sm text-wz-fg-muted">{{ t('payment.confirmProofHint') }}</p>

            <form class="grid max-w-xl gap-3" @submit.prevent="confirmPayment">
                <Input
                    id="received_amount"
                    v-model="confirmForm.received_amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    :error="confirmForm.errors.received_amount"
                >
                    <template #label>{{ t('payment.receivedAmount') }}</template>
                </Input>
                <Select
                    id="amount_disposition"
                    v-model="confirmForm.amount_disposition"
                    :error="confirmForm.errors.amount_disposition"
                >
                    <template #label>{{ t('payment.amountDisposition') }}</template>
                    <option value="">—</option>
                    <option value="partial">{{ t('payment.partial') }}</option>
                    <option value="overpaid">{{ t('payment.overpaid') }}</option>
                </Select>
                <Input
                    id="amount_note"
                    v-model="confirmForm.amount_note"
                    :error="confirmForm.errors.amount_note"
                >
                    <template #label>{{ t('payment.amountNote') }}</template>
                </Input>
                <div>
                    <Button type="submit" variant="primary" :disabled="confirmForm.processing">
                        {{ t('payment.confirmProof') }}
                    </Button>
                </div>
            </form>

            <form class="max-w-md space-y-2 border-t border-wz-border pt-4" @submit.prevent="rejectPayment">
                <Input id="reject_reason" v-model="rejectForm.reason" :error="rejectForm.errors.reason">
                    <template #label>{{ t('payment.rejectReason') }}</template>
                </Input>
                <Button type="submit" variant="danger" :disabled="rejectForm.processing">
                    {{ t('payment.rejectProof') }}
                </Button>
            </form>
        </section>

        <div class="mt-4 flex flex-wrap gap-2">
            <Button
                v-if="canConfirmBooking"
                variant="primary"
                @click="setStatus('confirmed')"
            >
                {{ t('owner.confirmBooking') }}
            </Button>
            <p
                v-else-if="booking.status === 'pending'"
                class="w-full text-sm text-wz-fg-muted"
            >
                {{ t('payment.confirmBookingNeedsPaid') }}
            </p>
            <Button
                v-if="booking.status !== 'completed' && booking.status !== 'cancelled'"
                variant="secondary"
                @click="setStatus('completed')"
            >
                {{ t('owner.completeBooking') }}
            </Button>
            <Button
                v-if="booking.status !== 'cancelled'"
                variant="danger"
                @click="setStatus('cancelled')"
            >
                {{ t('owner.cancelBooking') }}
            </Button>
        </div>

        <ConfirmDialog
            :show="showCancel"
            :title="t('owner.cancelBookingTitle')"
            :message="t('owner.cancelBookingMessage')"
            :confirm-label="t('owner.cancelBooking')"
            danger
            @confirm="confirmCancel"
            @cancel="showCancel = false"
        />
    </AppLayout>
</template>
