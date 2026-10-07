<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import LoadingState from '@/Components/Ui/LoadingState.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { useNavigationLoading } from '@/Composables/useNavigationLoading';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: {
        type: Object,
        default: () => ({
            status: '',
            search: '',
            per_page: 12,
        }),
    },
});

const { t, locale } = useI18n();
const cancelTarget = ref(null);
const { loading: filterLoading } = useNavigationLoading();

const form = reactive({
    status: props.filters.status || '',
    search: props.filters.search || '',
    per_page: props.filters.per_page || 12,
});

function applyFilters() {
    router.get(route('owner.bookings.index'), { ...form }, { preserveState: true, replace: true });
}

watch(() => form.per_page, () => applyFilters());

function setStatusFilter(status) {
    form.status = status;
    applyFilters();
}

function updateStatus(bookingId, status) {
    if (status === 'cancelled') {
        cancelTarget.value = bookingId;
        return;
    }
    router.put(
        route('owner.bookings.update', bookingId),
        { status },
        { preserveScroll: true },
    );
}

function confirmCancel() {
    if (!cancelTarget.value) return;
    router.put(
        route('owner.bookings.update', cancelTarget.value),
        { status: 'cancelled' },
        {
            preserveScroll: true,
            onFinish: () => {
                cancelTarget.value = null;
            },
        },
    );
}

const hasData = computed(() => props.bookings?.data?.length);

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

const tabs = [
    { value: '', labelKey: 'status.all' },
    { value: 'pending', labelKey: 'status.pending' },
    { value: 'confirmed', labelKey: 'status.confirmed' },
    { value: 'completed', labelKey: 'status.completed' },
    { value: 'cancelled', labelKey: 'status.cancelled' },
];
</script>

<template>
    <AppLayout :title="t('owner.bookingsTitle')">
        <Head :title="t('owner.bookingsTitle')" />

        <PageHeader
            :title="t('owner.bookingsTitle')"
            :subtitle="t('owner.bookingsSubtitle')"
        />

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Input
                    id="owner-booking-search"
                    v-model="form.search"
                    :placeholder="t('common.search')"
                    @keyup.enter="applyFilters"
                >
                    <template #label>{{ t('common.search') }}</template>
                </Input>
            </div>
            <select
                v-model.number="form.per_page"
                class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg"
            >
                <option :value="6">6</option>
                <option :value="12">12</option>
                <option :value="24">24</option>
            </select>
            <Button variant="secondary" @click="applyFilters">{{ t('owner.apply') }}</Button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.value || 'all'"
                type="button"
                class="rounded-xl border px-3 py-1.5 text-sm transition"
                :class="
                    form.status === tab.value
                        ? 'border-wz-brand bg-wz-brand text-wz-brand-fg'
                        : 'border-wz-border bg-wz-elevated text-wz-fg hover:bg-wz-muted'
                "
                @click="setStatusFilter(tab.value)"
            >
                {{ t(tab.labelKey) }}
            </button>
        </div>

        <LoadingState v-if="filterLoading" />

        <EmptyState
            v-else-if="!hasData"
            :title="t('owner.noBookings')"
            :description="t('common.emptyHint')"
        />

        <template v-else>
            <div class="space-y-3 md:hidden">
                <article v-for="b in bookings.data" :key="b.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">{{ b.workspace?.name ?? '—' }}</h3>
                        <Badge :tone="statusTone(b.status)">
                            {{ t(`status.${b.status}`, b.status) }}
                        </Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">
                        {{ b.user?.name }} · {{ b.hours }}h · $
                        {{ Number(b.total_price ?? 0).toFixed(2) }}
                    </p>
                    <p class="text-xs text-wz-fg-muted">{{ formatDate(b.created_at) }}</p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Button
                            v-if="b.status === 'pending'"
                            size="sm"
                            variant="primary"
                            @click="updateStatus(b.id, 'confirmed')"
                        >
                            {{ t('owner.confirmBooking') }}
                        </Button>
                        <Button
                            v-if="b.status === 'confirmed'"
                            size="sm"
                            variant="secondary"
                            @click="updateStatus(b.id, 'completed')"
                        >
                            {{ t('owner.completeBooking') }}
                        </Button>
                        <Button
                            v-if="b.status !== 'cancelled' && b.status !== 'completed'"
                            size="sm"
                            variant="danger"
                            @click="updateStatus(b.id, 'cancelled')"
                        >
                            {{ t('owner.cancelBooking') }}
                        </Button>
                        <Link :href="route('owner.bookings.show', b.id)">
                            <Button size="sm" variant="ghost">{{ t('bookings.view') }}</Button>
                        </Link>
                    </div>
                </article>
            </div>

            <div class="wz-surface hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.customer') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.hours') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.total') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.status') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="b in bookings.data"
                            :key="b.id"
                            class="border-t border-wz-border"
                        >
                            <td class="px-3 py-2 text-wz-fg">{{ b.id }}</td>
                            <td class="px-3 py-2 font-medium text-wz-fg">
                                {{ b.workspace?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-wz-fg">{{ b.user?.name ?? '—' }}</div>
                                <div class="text-xs text-wz-fg-muted">{{ b.user?.email ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2 text-wz-fg">{{ b.hours }}</td>
                            <td class="px-3 py-2 text-wz-fg">
                                $ {{ Number(b.total_price ?? 0).toFixed(2) }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge :tone="statusTone(b.status)">
                                    {{ t(`status.${b.status}`, b.status) }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="b.status === 'pending'"
                                        size="sm"
                                        variant="primary"
                                        @click="updateStatus(b.id, 'confirmed')"
                                    >
                                        {{ t('owner.confirmBooking') }}
                                    </Button>
                                    <Button
                                        v-if="b.status === 'confirmed'"
                                        size="sm"
                                        variant="secondary"
                                        @click="updateStatus(b.id, 'completed')"
                                    >
                                        {{ t('owner.completeBooking') }}
                                    </Button>
                                    <Button
                                        v-if="b.status !== 'cancelled' && b.status !== 'completed'"
                                        size="sm"
                                        variant="danger"
                                        @click="updateStatus(b.id, 'cancelled')"
                                    >
                                        {{ t('owner.cancelBooking') }}
                                    </Button>
                                    <Link :href="route('owner.bookings.show', b.id)">
                                        <Button size="sm" variant="ghost">
                                            {{ t('bookings.view') }}
                                        </Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination v-if="bookings?.links" :links="bookings.links" />

        <ConfirmDialog
            :show="Boolean(cancelTarget)"
            :title="t('owner.cancelBookingTitle')"
            :message="t('owner.cancelBookingMessage')"
            :confirm-label="t('owner.cancelBooking')"
            danger
            @confirm="confirmCancel"
            @cancel="cancelTarget = null"
        />
    </AppLayout>
</template>
