<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

const props = defineProps({
    logs: Object,
    filters: Object,
    actions: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = reactive({
    action: props.filters?.action || '',
    actor_id: props.filters?.actor_id || '',
});

function applyFilters() {
    router.get(route('admin.audit-logs.index'), form, { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();
    if (form.action) params.set('action', form.action);
    if (form.actor_id) params.set('actor_id', String(form.actor_id));
    const qs = params.toString();
    return route('admin.audit-logs.export') + (qs ? `?${qs}` : '');
}
</script>

<template>
    <AppLayout :title="t('nav.auditLogs')">
        <Head :title="t('nav.auditLogs')" />

        <PageHeader :title="t('nav.auditLogs')" :subtitle="t('admin.auditLogsHint')">
            <template #actions>
                <a :href="exportUrl()">
                    <Button variant="secondary">{{ t('admin.exportCsv') }}</Button>
                </a>
            </template>
        </PageHeader>

        <form class="wz-surface mb-4 grid gap-3 p-4 sm:grid-cols-3" @submit.prevent="applyFilters">
            <Select id="filter-action" v-model="form.action">
                <template #label>{{ t('admin.auditAction') }}</template>
                <option value="">{{ t('common.all') }}</option>
                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
            </Select>
            <Input id="filter-actor" v-model="form.actor_id" type="number" min="1">
                <template #label>{{ t('admin.auditActorId') }}</template>
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
                        <th class="px-3 py-2 text-start">{{ t('common.date') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.auditAction') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.auditActor') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.auditSubject') }}</th>
                        <th class="px-3 py-2 text-start">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ log.id }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ log.created_at || '—' }}</td>
                        <td class="px-3 py-2 font-medium">{{ log.action }}</td>
                        <td class="px-3 py-2">
                            <div>{{ log.actor?.name || '—' }}</div>
                            <div class="text-xs text-wz-fg-muted">
                                {{ log.actor?.email || '' }}
                                <span v-if="log.actor_role"> · {{ log.actor_role }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <span v-if="log.subject_type">{{ log.subject_type }}#{{ log.subject_id }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="px-3 py-2">{{ log.ip || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="logs.links" />
        </div>
    </AppLayout>
</template>
