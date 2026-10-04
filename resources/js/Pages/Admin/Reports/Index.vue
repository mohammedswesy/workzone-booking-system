<script setup>
import { computed, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    kpis: { type: Object, default: () => ({}) },
    series: { type: Array, default: () => [] },
    topWorkspaces: { type: Array, default: () => [] },
    topOwners: { type: Array, default: () => [] },
    workspaces: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = reactive({
    from: props.filters.from || '',
    to: props.filters.to || '',
    status: props.filters.status || '',
    workspace_id: props.filters.workspace_id || '',
    owner_id: props.filters.owner_id || '',
});

function apply() {
    router.get(route('admin.reports.index'), { ...form }, { preserveState: true, replace: true });
}

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(form).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });
    window.location.href = `${route('admin.reports.export')}?${params.toString()}`;
}

const totalInRange = computed(() =>
    (props.series || []).reduce((a, b) => a + Number(b.c || 0), 0),
);

const maxSeries = computed(() =>
    Math.max(1, ...(props.series || []).map((row) => Number(row.c || 0))),
);
</script>

<template>
    <AppLayout :title="t('admin.reportsTitle')">
        <Head :title="t('admin.reportsTitle')" />

        <PageHeader :title="t('admin.reportsTitle')" :subtitle="t('admin.reportsSubtitle')">
            <template #actions>
                <Button variant="secondary" @click="exportCsv">{{ t('admin.exportCsv') }}</Button>
            </template>
        </PageHeader>

        <div class="wz-surface mb-6 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <Input id="report-from" v-model="form.from" type="date">
                <template #label>{{ t('admin.from') }}</template>
            </Input>
            <Input id="report-to" v-model="form.to" type="date">
                <template #label>{{ t('admin.to') }}</template>
            </Input>
            <Select id="report-status" v-model="form.status">
                <template #label>{{ t('bookings.status') }}</template>
                <option value="">{{ t('status.all') }}</option>
                <option value="pending">{{ t('status.pending') }}</option>
                <option value="confirmed">{{ t('status.confirmed') }}</option>
                <option value="cancelled">{{ t('status.cancelled') }}</option>
                <option value="completed">{{ t('status.completed') }}</option>
                <option value="no_show">{{ t('status.no_show') }}</option>
            </Select>
            <Select
                id="report-workspace"
                :model-value="form.workspace_id"
                @update:model-value="form.workspace_id = $event"
            >
                <template #label>{{ t('bookings.workspace') }}</template>
                <option value="">{{ t('status.all') }}</option>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
            </Select>
            <Select
                id="report-owner"
                :model-value="form.owner_id"
                @update:model-value="form.owner_id = $event"
            >
                <template #label>{{ t('admin.owner') }}</template>
                <option value="">{{ t('status.all') }}</option>
                <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
            </Select>
            <div class="flex items-end">
                <Button variant="primary" block @click="apply">{{ t('owner.apply') }}</Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <StatCard :label="t('admin.statUsers')" :value="kpis.users ?? 0" />
            <StatCard :label="t('admin.statOwners')" :value="kpis.owners ?? 0" />
            <StatCard :label="t('admin.statSpaces')" :value="kpis.workspaces ?? 0" />
            <StatCard :label="t('admin.statBookings')" :value="kpis.bookings ?? 0" />
            <StatCard
                :label="t('admin.revenue')"
                :value="`$ ${Number(kpis.revenue ?? 0).toFixed(2)}`"
            />
            <StatCard
                :label="t('admin.avgBooking')"
                :value="`$ ${Number(kpis.average_booking_value ?? 0).toFixed(2)}`"
            />
        </div>

        <section class="wz-surface mt-6 p-5">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-wz-fg">
                    {{ t('admin.dailyTrend') }}
                </h2>
                <div class="text-sm text-wz-fg-muted">
                    {{ t('admin.totalInRange') }}: {{ totalInRange }}
                </div>
            </div>
            <EmptyState v-if="!series?.length" :title="t('admin.noData')" />
            <div v-else class="space-y-2">
                <div v-for="row in series" :key="row.d" class="flex items-center gap-3">
                    <div class="w-28 text-xs text-wz-fg-muted">{{ row.d }}</div>
                    <div class="h-2 flex-1 rounded bg-wz-muted">
                        <div
                            class="h-2 rounded bg-wz-brand"
                            :style="{ width: `${Math.min(100, (Number(row.c) / maxSeries) * 100)}%` }"
                        />
                    </div>
                    <div class="w-28 text-end text-xs text-wz-fg">
                        {{ row.c }} / $ {{ Number(row.revenue || 0).toFixed(0) }}
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="wz-surface p-5">
                <h2 class="mb-3 font-display text-lg font-semibold text-wz-fg">
                    {{ t('admin.topSpaces') }}
                </h2>
                <EmptyState v-if="!topWorkspaces?.length" :title="t('admin.noData')" />
                <ul v-else class="divide-y divide-wz-border">
                    <li
                        v-for="w in topWorkspaces"
                        :key="w.id"
                        class="flex items-center justify-between gap-3 py-2 text-sm"
                    >
                        <span class="text-wz-fg">{{ w.name }}</span>
                        <span class="text-wz-fg-muted">
                            {{ w.count }} · $ {{ Number(w.sum).toFixed(2) }}
                        </span>
                    </li>
                </ul>
            </section>
            <section class="wz-surface p-5">
                <h2 class="mb-3 font-display text-lg font-semibold text-wz-fg">
                    {{ t('admin.topOwners') }}
                </h2>
                <EmptyState v-if="!topOwners?.length" :title="t('admin.noData')" />
                <ul v-else class="divide-y divide-wz-border">
                    <li
                        v-for="o in topOwners"
                        :key="o.id"
                        class="flex items-center justify-between gap-3 py-2 text-sm"
                    >
                        <span class="text-wz-fg">{{ o.name }}</span>
                        <span class="text-wz-fg-muted">
                            {{ o.count }} · $ {{ Number(o.sum).toFixed(2) }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
