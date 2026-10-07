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

const canConfirm = computed(
    () => props.booking.status === 'pending' && props.booking.payment_status === 'paid',
);
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
                        v-if="canConfirm"
                        variant="primary"
                        :disabled="busy"
                        @click="updateStatus('confirmed')"
                    >
                        {{ t('owner.confirmBooking') }}
                    </Button>
                    <p
                        v-else-if="booking.status === 'pending' && booking.payment_status !== 'paid'"
                        class="w-full text-sm text-wz-fg-muted"
                    >
                        {{ t('owner.confirmRequiresPaid') }}
                    </p>
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
                    {{ t('payment.platformStatus') }}
                </h2>
                <Badge :tone="paymentTone(booking.payment_status)">
                    {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                </Badge>
                <p class="text-sm text-wz-fg-muted">{{ t('payment.ownerCannotSeeProof') }}</p>
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
