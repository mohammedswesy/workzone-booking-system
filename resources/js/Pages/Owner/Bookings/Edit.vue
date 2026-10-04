<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    statuses: {
        type: Array,
        default: () => ['confirmed', 'completed', 'cancelled'],
    },
});

const { t } = useI18n();

const form = useForm({
    status: props.booking.status ?? 'pending',
});

function submit() {
    form.put(route('owner.bookings.update', props.booking.id));
}
</script>

<template>
    <AppLayout :title="t('owner.reviewStatus')">
        <Head :title="t('owner.reviewStatus')" />

        <PageHeader
            :title="t('owner.reviewStatus')"
            :subtitle="`#${booking.id} · ${booking.workspace?.name || ''}`"
        >
            <template #actions>
                <Link :href="route('owner.bookings.show', booking.id)">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <p class="text-sm text-wz-fg-muted">{{ t('owner.bookingsSubtitle') }}</p>

            <Select
                id="owner-booking-status"
                v-model="form.status"
                :error="form.errors.status"
                :disabled="form.processing"
            >
                <template #label>{{ t('bookings.status') }}</template>
                <option v-for="s in statuses" :key="s" :value="s">
                    {{ t(`status.${s}`, s) }}
                </option>
            </Select>

            <Input
                id="owner-booking-hours"
                :model-value="String(booking.hours ?? '')"
                disabled
            >
                <template #label>{{ t('owner.hoursFixed') }}</template>
            </Input>

            <Input
                id="owner-booking-total"
                :model-value="`$ ${Number(booking.total_price ?? 0).toFixed(2)}`"
                disabled
            >
                <template #label>{{ t('owner.priceFixed') }}</template>
            </Input>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('owner.bookings.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
