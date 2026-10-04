<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    booking: { type: Object, required: true },
});

const { t, locale } = useI18n();

const showCancel = ref(false);
const cancelling = ref(false);

const methods = computed(() => props.booking.workspace?.payment_methods || []);

const manualForm = useForm({
    method: methods.value[0] || 'bank_transfer',
    proof: null,
});

const fileName = ref('');

const statusTone = computed(() => {
    const map = {
        pending: 'warning',
        confirmed: 'success',
        cancelled: 'danger',
        completed: 'brand',
        no_show: 'neutral',
    };
    return map[props.booking.status] || 'neutral';
});

const paymentTone = computed(() => {
    const map = {
        unpaid: 'warning',
        pending: 'accent',
        paid: 'success',
        failed: 'danger',
        refunded: 'neutral',
    };
    return map[props.booking.payment_status] || 'neutral';
});

const canPay = computed(
    () =>
        props.booking.status === 'pending' &&
        ['unpaid', 'failed'].includes(props.booking.payment_status),
);

const waitingReview = computed(() => props.booking.payment_status === 'pending');

const canCancel = computed(() =>
    ['pending', 'confirmed'].includes(props.booking.status),
);

const isCash = computed(() => manualForm.method === 'cash');

const latestRejected = computed(() => {
    const list = props.booking.payments || [];
    return list.find((p) => p.status === 'failed' && p.rejection_reason) || null;
});

const methodLabel = (method) => {
    const map = {
        bank_transfer: 'payment.methodBank',
        wallet: 'payment.methodWallet',
        cash: 'payment.methodCash',
    };
    return t(map[method] || method);
};

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

function onProofChange(e) {
    const file = e.target.files?.[0] ?? null;
    manualForm.proof = file;
    fileName.value = file?.name || '';
}

function submitManual() {
    manualForm.post(route('user.payments.manual.store', props.booking.id), {
        forceFormData: true,
    });
}

function confirmCancel() {
    cancelling.value = true;
    router.delete(route('user.bookings.destroy', props.booking.id), {
        onFinish: () => {
            cancelling.value = false;
            showCancel.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :title="`${t('bookings.details')} #${booking.id}`">
        <Head :title="`${t('bookings.details')} #${booking.id}`" />

        <PageHeader
            :title="`${t('bookings.details')} #${booking.id}`"
            :subtitle="booking.workspace?.name || ''"
        >
            <template #actions>
                <Link :href="route('user.bookings.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
                <Link
                    v-if="booking.status === 'pending'"
                    :href="route('user.bookings.edit', booking.id)"
                >
                    <Button variant="ghost">{{ t('bookings.edit') }}</Button>
                </Link>
                <Button
                    v-if="canCancel"
                    variant="danger"
                    :disabled="cancelling"
                    @click="showCancel = true"
                >
                    {{ t('bookings.cancel') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="wz-surface space-y-4 p-5 lg:col-span-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.workspace') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ booking.workspace?.name }}</div>
                        <div class="text-sm text-wz-fg-muted">{{ booking.workspace?.location }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.status') }}</div>
                        <div class="mt-1">
                            <Badge :tone="statusTone">
                                {{ t(`status.${booking.status}`, booking.status) }}
                            </Badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.paymentStatus') }}</div>
                        <div class="mt-1">
                            <Badge :tone="paymentTone">
                                {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                            </Badge>
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.total') }}</div>
                        <div class="mt-1 font-display text-xl font-semibold text-wz-fg">
                            $ {{ Number(booking.total_price ?? 0).toFixed(2) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.start') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ formatDate(booking.start_at) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.end') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ formatDate(booking.end_at) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.hours') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ booking.hours }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.seats') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ booking.seats ?? 1 }}</div>
                    </div>
                </div>

                <div
                    v-if="(canPay || waitingReview) && booking.workspace?.payment_details_ready"
                    class="rounded-xl border border-wz-border bg-wz-muted/50 p-4"
                >
                    <h2 class="font-display text-base font-semibold text-wz-fg">
                        {{ t('payment.instructionsTitle') }}
                    </h2>
                    <p class="mt-1 text-sm text-wz-fg-muted">{{ t('payment.instructionsHint') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Badge
                            v-for="m in methods"
                            :key="m"
                            tone="brand"
                        >
                            {{ methodLabel(m) }}
                        </Badge>
                    </div>
                    <pre
                        class="mt-3 whitespace-pre-wrap rounded-xl border border-wz-border bg-wz-elevated px-3 py-3 font-sans text-sm text-wz-fg"
                    >{{ booking.workspace?.payment_instructions }}</pre>
                </div>
                <div
                    v-else-if="canPay || waitingReview"
                    class="rounded-xl border border-wz-warning/40 bg-wz-accent-soft p-4"
                    role="status"
                >
                    <h2 class="font-display text-base font-semibold text-wz-fg">
                        {{ t('payment.detailsPendingTitle') }}
                    </h2>
                    <p class="mt-1 text-sm text-wz-fg-muted">{{ t('payment.detailsPendingHint') }}</p>
                </div>
            </section>

            <section class="wz-surface space-y-4 p-5">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('payment.title') }}
                </h2>

                <Badge :tone="paymentTone">
                    {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                </Badge>

                <div
                    v-if="latestRejected && canPay"
                    class="rounded-xl border border-wz-danger/40 bg-red-50 px-3 py-2 text-sm text-wz-danger dark:bg-red-950/40 dark:text-wz-danger"
                >
                    <p class="font-medium">{{ t('payment.rejectedNotice') }}</p>
                    <p class="mt-1 text-wz-fg">{{ latestRejected.rejection_reason }}</p>
                </div>

                <p
                    v-if="waitingReview"
                    class="rounded-xl bg-wz-accent-soft px-3 py-2 text-sm text-wz-fg"
                >
                    {{ t('payment.waitingReview') }}
                </p>

                <p
                    v-else-if="booking.payment_status === 'paid'"
                    class="rounded-xl bg-wz-brand-soft px-3 py-2 text-sm text-wz-fg"
                >
                    {{ t('payment.paid') }}
                </p>

                <form
                    v-else-if="canPay && booking.workspace?.payment_details_ready"
                    class="space-y-3"
                    @submit.prevent="submitManual"
                >
                    <div class="grid gap-2">
                        <span class="text-sm font-medium text-wz-fg">{{ t('payment.method') }}</span>
                        <label
                            v-for="m in methods"
                            :key="m"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm text-wz-fg"
                        >
                            <input
                                v-model="manualForm.method"
                                type="radio"
                                class="text-wz-brand disabled:opacity-55"
                                :value="m"
                                :disabled="manualForm.processing"
                            />
                            {{ methodLabel(m) }}
                        </label>
                        <p v-if="manualForm.errors.method" class="text-xs text-wz-danger">
                            {{ manualForm.errors.method }}
                        </p>
                    </div>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-wz-fg">
                            {{ t('payment.uploadProof') }}
                            <span v-if="isCash" class="font-normal text-wz-fg-muted">
                                ({{ t('payment.proofOptionalCash') }})
                            </span>
                        </span>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="wz-focus block w-full text-sm text-wz-fg file:me-3 file:rounded-lg file:border-0 file:bg-wz-brand-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-wz-brand disabled:cursor-not-allowed disabled:opacity-55"
                            :disabled="manualForm.processing"
                            @change="onProofChange"
                        />
                        <span v-if="fileName" class="text-xs text-wz-fg-muted">{{ fileName }}</span>
                        <span v-else class="text-xs text-wz-fg-muted">{{ t('payment.chooseFile') }}</span>
                    </label>
                    <p v-if="manualForm.errors.proof" class="text-xs text-wz-danger">
                        {{ manualForm.errors.proof }}
                    </p>

                    <Button
                        type="submit"
                        variant="primary"
                        block
                        :disabled="manualForm.processing || (!isCash && !manualForm.proof)"
                    >
                        {{ isCash && !manualForm.proof ? t('payment.markCashPending') : t('payment.submitProof') }}
                    </Button>
                </form>

                <p v-else class="text-sm text-wz-fg-muted">
                    {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                </p>
            </section>
        </div>

        <ConfirmDialog
            :show="showCancel"
            :title="t('bookings.cancelTitle')"
            :message="t('bookings.cancelMessage')"
            :confirm-label="t('bookings.confirmCancel')"
            :cancel-label="t('common.cancel')"
            danger
            @confirm="confirmCancel"
            @cancel="showCancel = false"
        />
    </AppLayout>
</template>
