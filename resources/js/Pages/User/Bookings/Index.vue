<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import BookingCard from '@/Components/Ui/BookingCard.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    bookings: Object,
    filters: Object,
});

const { t } = useI18n();

const status = ref(props.filters?.status || '');
const cancelTarget = ref(null);
const cancelling = ref(false);

const statusOptions = computed(() => [
    { value: '', label: t('status.all') },
    { value: 'pending', label: t('status.pending') },
    { value: 'confirmed', label: t('status.confirmed') },
    { value: 'cancelled', label: t('status.cancelled') },
    { value: 'completed', label: t('status.completed') },
    { value: 'no_show', label: t('status.no_show') },
]);

const items = computed(() => props.bookings?.data ?? []);

function applyFilter(value) {
    status.value = value;
    router.get(
        route('user.bookings.index'),
        {
            status: value || undefined,
            per_page: props.filters?.per_page || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function askCancel(booking) {
    cancelTarget.value = booking;
}

function closeCancel() {
    if (cancelling.value) return;
    cancelTarget.value = null;
}

function confirmCancel() {
    if (!cancelTarget.value) return;
    cancelling.value = true;
    router.delete(route('user.bookings.destroy', cancelTarget.value.id), {
        preserveScroll: true,
        onFinish: () => {
            cancelling.value = false;
            cancelTarget.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :title="t('bookings.title')">
        <Head :title="t('bookings.title')" />

        <PageHeader :title="t('bookings.title')" :subtitle="t('bookings.subtitle')">
            <template #actions>
                <Link :href="route('user.bookings.create')">
                    <Button variant="primary">{{ t('bookings.new') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 max-w-xs">
            <Select
                id="booking-status-filter"
                :model-value="status"
                :options="statusOptions"
                @update:model-value="applyFilter"
            >
                <template #label>{{ t('bookings.filterStatus') }}</template>
            </Select>
        </div>

        <EmptyState
            v-if="!items.length"
            :title="t('bookings.empty')"
            :description="t('bookings.emptyHint')"
        >
            <template #action>
                <Link :href="route('spaces.index')">
                    <Button variant="primary" size="sm">{{ t('bookings.browseSpaces') }}</Button>
                </Link>
            </template>
        </EmptyState>

        <div v-else class="space-y-3">
            <BookingCard
                v-for="b in items"
                :key="b.id"
                :booking="b"
                @cancel="askCancel"
            />
        </div>

        <Pagination :links="bookings.links" />

        <ConfirmDialog
            :show="Boolean(cancelTarget)"
            :title="t('bookings.cancelTitle')"
            :message="t('bookings.cancelMessage')"
            :confirm-label="t('bookings.confirmCancel')"
            :cancel-label="t('common.cancel')"
            danger
            @confirm="confirmCancel"
            @cancel="closeCancel"
        />
    </AppLayout>
</template>
