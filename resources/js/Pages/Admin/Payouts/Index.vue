<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

const props = defineProps({
    payouts: Object,
    filters: Object,
    owners: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = reactive({
    status: props.filters?.status || '',
    owner_id: props.filters?.owner_id || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
});

function applyFilters() {
    router.get(route('admin.payouts.index'), form, { preserveState: true, replace: true });
}

function statusTone(status) {
    const map = { requested: 'warning', approved: 'accent', paid: 'success', rejected: 'danger' };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="t('admin.payoutsTitle')">
        <Head :title="t('admin.payoutsTitle')" />
        <PageHeader :title="t('admin.payoutsTitle')" :subtitle="t('admin.payoutsHint')" />

        <form class="wz-surface mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
            <Select id="filter-status" v-model="form.status">
                <template #label>{{ t('common.status') }}</template>
                <option value="">{{ t('common.all') }}</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
            </Select>
            <Select id="filter-owner" v-model="form.owner_id">
                <template #label>{{ t('admin.owner') }}</template>
                <option value="">{{ t('common.all') }}</option>
                <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
            </Select>
            <Input id="from" v-model="form.from" type="date">
                <template #label>{{ t('admin.from') }}</template>
            </Input>
            <Input id="to" v-model="form.to" type="date">
                <template #label>{{ t('admin.to') }}</template>
            </Input>
            <div class="flex items-end">
                <Button type="submit" variant="secondary" class="w-full">{{ t('common.apply') }}</Button>
            </div>
        </form>

        <div class="wz-surface overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">#</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.owner') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.payoutAmount') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.status') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payouts.data" :key="p.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ p.id }}</td>
                        <td class="px-3 py-2">{{ p.owner?.name }}</td>
                        <td class="px-3 py-2">{{ p.amount_approved || p.amount_requested }}</td>
                        <td class="px-3 py-2">
                            <Badge :tone="statusTone(p.status)">{{ p.status }}</Badge>
                        </td>
                        <td class="px-3 py-2">
                            <Link :href="route('admin.payouts.show', p.id)" class="text-wz-brand hover:underline">
                                {{ t('common.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="payouts.links" />
        </div>
    </AppLayout>
</template>
