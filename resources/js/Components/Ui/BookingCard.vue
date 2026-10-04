<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Badge from './Badge.vue';
import Button from './Button.vue';

const props = defineProps({
    booking: { type: Object, required: true },
});

const emit = defineEmits(['cancel']);

const { t, locale } = useI18n();

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

const canCancel = computed(() =>
    ['pending', 'confirmed'].includes(props.booking.status),
);

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

function money(value) {
    return `$ ${Number(value ?? 0).toFixed(2)}`;
}
</script>

<template>
    <article class="wz-surface flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0 space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="truncate font-display text-lg font-semibold text-wz-fg">
                    {{ booking.workspace?.name || t('bookings.workspace') }}
                </h3>
                <Badge :tone="statusTone">
                    {{ t(`status.${booking.status}`, booking.status) }}
                </Badge>
                <Badge :tone="paymentTone">
                    {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                </Badge>
            </div>
            <p class="text-sm text-wz-fg-muted">
                {{ formatDate(booking.start_at) }}
                <span class="mx-1 text-wz-border">·</span>
                {{ booking.hours }} {{ t('bookings.hours').toLowerCase() }}
                <span class="mx-1 text-wz-border">·</span>
                {{ booking.seats ?? 1 }} {{ t('bookings.seats').toLowerCase() }}
                <span class="mx-1 text-wz-border">·</span>
                <span class="font-medium text-wz-fg">{{ money(booking.total_price) }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Link :href="route('user.bookings.show', booking.id)">
                <Button variant="secondary" size="sm">{{ t('bookings.view') }}</Button>
            </Link>
            <Link
                v-if="booking.status === 'pending'"
                :href="route('user.bookings.edit', booking.id)"
            >
                <Button variant="ghost" size="sm">{{ t('bookings.edit') }}</Button>
            </Link>
            <Button
                v-if="canCancel"
                variant="danger"
                size="sm"
                @click="emit('cancel', booking)"
            >
                {{ t('bookings.cancel') }}
            </Button>
        </div>
    </article>
</template>
