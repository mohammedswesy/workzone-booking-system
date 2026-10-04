<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { ref } from 'vue';

const props = defineProps({ booking: Object });

const { t } = useI18n();
const showCancel = ref(false);

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

        <div class="mt-4 flex flex-wrap gap-2">
            <Button
                v-if="booking.status === 'pending'"
                variant="primary"
                @click="setStatus('confirmed')"
            >
                {{ t('owner.confirmBooking') }}
            </Button>
            <Button
                v-if="booking.status !== 'completed'"
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
