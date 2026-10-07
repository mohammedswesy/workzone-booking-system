<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

const props = defineProps({
    owners: Object,
    filters: Object,
});

const { t } = useI18n();

const form = reactive({
    search: props.filters?.search || '',
});

function apply() {
    router.get(route('admin.owner-accounts.index'), form, { preserveState: true, replace: true });
}

function money(value) {
    return `$ ${Number(value ?? 0).toFixed(2)}`;
}

function statusTone(status) {
    const map = { requested: 'warning', approved: 'accent', paid: 'success', rejected: 'danger' };
    return map[status] || 'neutral';
}
</script>

<template>
    <AppLayout :title="t('admin.ownerAccountsTitle')">
        <Head :title="t('admin.ownerAccountsTitle')" />
        <PageHeader
            :title="t('admin.ownerAccountsTitle')"
            :subtitle="t('admin.ownerAccountsHint')"
        />

        <form class="wz-surface mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end" @submit.prevent="apply">
            <div class="min-w-0 flex-1">
                <Input id="search" v-model="form.search">
                    <template #label>{{ t('common.search') }}</template>
                </Input>
            </div>
            <Button type="submit" variant="secondary">{{ t('common.apply') }}</Button>
        </form>

        <div class="wz-surface overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ t('admin.owner') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaEarnings') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaCommission') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaRefunds') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaPaidOut') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.availableBalance') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('owner.pendingBalance') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.oaOpenPayout') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in owners.data" :key="o.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">
                            <div class="font-medium text-wz-fg">{{ o.name }}</div>
                            <div class="text-xs text-wz-fg-muted">{{ o.email }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.earnings) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.commission_taken) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.refunds) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.paid_out) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.available) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ money(o.pending) }}</td>
                        <td class="px-3 py-2">
                            <template v-if="o.open_payout">
                                <Badge :tone="statusTone(o.open_payout.status)">
                                    {{ money(o.open_payout.amount) }} · {{ o.open_payout.status }}
                                </Badge>
                            </template>
                            <span v-else class="text-wz-fg-muted">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <Link
                                :href="route('admin.owner-accounts.show', o.id)"
                                class="text-wz-brand hover:underline"
                            >
                                {{ t('common.view') }}
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="owners.links" />
        </div>
    </AppLayout>
</template>
