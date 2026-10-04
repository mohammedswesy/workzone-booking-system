<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
const busy = ref(false);
const rejectReason = ref('');
const showReject = ref(false);

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

function proofUrl(path) {
    if (!path) return null;
    if (String(path).startsWith('http') || String(path).startsWith('/storage/')) return path;
    return `/storage/${path}`;
}

const latestManual = computed(() => {
    const list = props.booking.payments || [];
    return (
        list.find((p) => p.provider === 'manual' && p.status === 'pending') ||
        list.find((p) => p.provider === 'manual') ||
        null
    );
});

function updateStatus(status) {
    if (status === 'cancelled') {
        showCancel.value = true;
        return;
    }
    busy.value = true;
    router.put(
        route('owner.bookings.update', props.booking.id),
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function confirmCancel() {
    busy.value = true;
    router.put(
        route('owner.bookings.update', props.booking.id),
        { status: 'cancelled' },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                showCancel.value = false;
            },
        },
    );
}

function confirmProof() {
    if (!latestManual.value) return;
    busy.value = true;
    router.post(
        route('payments.manual.confirm', latestManual.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function rejectProof() {
    if (!latestManual.value || !rejectReason.value.trim()) return;
    busy.value = true;
    router.post(
        route('payments.manual.reject', latestManual.value.id),
        { reason: rejectReason.value.trim() },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
                showReject.value = false;
                rejectReason.value = '';
            },
        },
    );
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
                <Link :href="route('owner.bookings.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
                <Link :href="route('owner.bookings.edit', booking.id)">
                    <Button variant="ghost">{{ t('owner.reviewStatus') }}</Button>
                </Link>
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
                        <div class="text-sm text-wz-fg-muted">{{ t('owner.customer') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ booking.user?.name }}</div>
                        <div class="text-sm text-wz-fg-muted">{{ booking.user?.email }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('owner.hoursFixed') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">{{ booking.hours }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('owner.priceFixed') }}</div>
                        <div class="mt-1 font-medium text-wz-fg">
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
                </div>

                <div class="flex flex-wrap gap-2 border-t border-wz-border pt-4">
                    <Button
                        v-if="booking.status === 'pending'"
                        variant="primary"
                        :disabled="busy"
                        @click="updateStatus('confirmed')"
                    >
                        {{ t('owner.confirmBooking') }}
                    </Button>
                    <Button
                        v-if="booking.status === 'confirmed'"
                        variant="secondary"
                        :disabled="busy"
                        @click="updateStatus('completed')"
                    >
                        {{ t('owner.completeBooking') }}
                    </Button>
                    <Button
                        v-if="booking.status !== 'cancelled' && booking.status !== 'completed'"
                        variant="danger"
                        :disabled="busy"
                        @click="updateStatus('cancelled')"
                    >
                        {{ t('owner.cancelBooking') }}
                    </Button>
                </div>
            </section>

            <section class="wz-surface space-y-3 p-5">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('payment.reviewProof') }}
                </h2>

                <template v-if="latestManual">
                    <div class="text-sm text-wz-fg-muted">
                        {{ t('payment.provider') }}:
                        <span class="text-wz-fg">{{ t('payment.manual') }}</span>
                    </div>
                    <Badge :tone="paymentTone(latestManual.status)">
                        {{ t(`payment.${latestManual.status}`, latestManual.status) }}
                    </Badge>

                    <a
                        v-if="proofUrl(latestManual.proof_path)"
                        :href="proofUrl(latestManual.proof_path)"
                        target="_blank"
                        rel="noopener"
                        class="block overflow-hidden rounded-xl border border-wz-border"
                    >
                        <img
                            v-if="!String(latestManual.proof_path).endsWith('.pdf')"
                            :src="proofUrl(latestManual.proof_path)"
                            alt=""
                            class="max-h-56 w-full object-contain bg-wz-muted"
                        />
                        <span v-else class="block px-3 py-6 text-center text-sm text-wz-brand">
                            PDF
                        </span>
                    </a>
                    <p v-else class="text-sm text-wz-fg-muted">{{ t('payment.noProof') }}</p>

                    <div v-if="latestManual.status === 'pending'" class="space-y-2">
                        <Button variant="primary" block :disabled="busy" @click="confirmProof">
                            {{ t('payment.confirmProof') }}
                        </Button>
                        <Button
                            variant="danger"
                            block
                            :disabled="busy"
                            @click="showReject = !showReject"
                        >
                            {{ t('payment.rejectProof') }}
                        </Button>
                        <div v-if="showReject" class="space-y-2">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-wz-fg">
                                    {{ t('payment.rejectReason') }}
                                </span>
                                <textarea
                                    v-model="rejectReason"
                                    rows="3"
                                    maxlength="500"
                                    class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:opacity-55"
                                    :placeholder="t('payment.rejectReasonPlaceholder')"
                                    :disabled="busy"
                                />
                            </label>
                            <Button
                                variant="danger"
                                block
                                :disabled="busy || !rejectReason.trim()"
                                @click="rejectProof"
                            >
                                {{ t('payment.rejectProof') }}
                            </Button>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-wz-fg-muted">{{ t('payment.noProof') }}</p>
            </section>
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
