<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { ref } from 'vue';

defineProps({
    bookings: Object,
});

const { t } = useI18n();
const cancelTarget = ref(null);

function updateStatus(id, status) {
    if (status === 'cancelled') {
        cancelTarget.value = id;
        return;
    }
    router.put(route('admin.bookings.update', id), { status }, { preserveScroll: true });
}

function confirmCancel() {
    if (!cancelTarget.value) return;
    router.put(
        route('admin.bookings.update', cancelTarget.value),
        { status: 'cancelled' },
        {
            preserveScroll: true,
            onFinish: () => {
                cancelTarget.value = null;
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
</script>

<template>
    <AppLayout :title="t('admin.bookingsTitle')">
        <Head :title="t('admin.bookingsTitle')" />

        <PageHeader
            :title="t('admin.bookingsTitle')"
            :subtitle="t('admin.bookingsSubtitle')"
        />

        <EmptyState
            v-if="!bookings?.data?.length"
            :title="t('common.empty')"
            :description="t('common.emptyHint')"
        />

        <template v-else>
            <div class="space-y-3 md:hidden">
                <article v-for="b in bookings.data" :key="b.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">#{{ b.id }} · {{ b.workspace?.name }}</h3>
                        <Badge :tone="statusTone(b.status)">
                            {{ t(`status.${b.status}`, b.status) }}
                        </Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">
                        {{ b.user?.name }} · {{ b.hours }}h · $
                        {{ Number(b.total_price ?? 0).toFixed(2) }}
                    </p>
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
                            v-if="b.status !== 'cancelled'"
                            size="sm"
                            variant="danger"
                            @click="updateStatus(b.id, 'cancelled')"
                        >
                            {{ t('owner.cancelBooking') }}
                        </Button>
                        <Link :href="route('admin.bookings.show', b.id)">
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
                        <tr v-for="b in bookings.data" :key="b.id" class="border-t border-wz-border">
                            <td class="px-3 py-2 text-wz-fg">{{ b.id }}</td>
                            <td class="px-3 py-2 font-medium text-wz-fg">{{ b.workspace?.name }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ b.user?.name }}</td>
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
                                        v-if="b.status !== 'cancelled'"
                                        size="sm"
                                        variant="danger"
                                        @click="updateStatus(b.id, 'cancelled')"
                                    >
                                        {{ t('owner.cancelBooking') }}
                                    </Button>
                                    <Link :href="route('admin.bookings.show', b.id)">
                                        <Button size="sm" variant="ghost">{{ t('bookings.view') }}</Button>
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="bookings.links" />

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
