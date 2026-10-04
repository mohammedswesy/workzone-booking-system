<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';

const props = defineProps({
    spaces: Object,
    filters: Object,
});

const { t } = useI18n();
const search = ref(props.filters?.search ?? '');

function apply() {
    router.get(
        route('admin.workspaces.index'),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}

function statusLabel(status) {
    return t(`owner.${status}`, status);
}
</script>

<template>
    <AppLayout :title="t('admin.workspacesTitle')">
        <Head :title="t('admin.workspacesTitle')" />

        <PageHeader
            :title="t('admin.workspacesTitle')"
            :subtitle="t('admin.workspacesSubtitle')"
        >
            <template #actions>
                <Link :href="route('admin.workspaces.create')">
                    <Button variant="primary">{{ t('admin.createWorkspace') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Input
                    id="admin-space-search"
                    v-model="search"
                    :placeholder="t('admin.searchSpaces')"
                    @keyup.enter="apply"
                >
                    <template #label>{{ t('common.search') }}</template>
                </Input>
            </div>
            <Button variant="secondary" @click="apply">{{ t('owner.apply') }}</Button>
        </div>

        <EmptyState
            v-if="!spaces?.data?.length"
            :title="t('common.empty')"
            :description="t('admin.workspacesSubtitle')"
        />

        <template v-else>
            <div class="space-y-3 md:hidden">
                <article v-for="s in spaces.data" :key="s.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">{{ s.name }}</h3>
                        <Badge v-if="s.status" tone="neutral">{{ statusLabel(s.status) }}</Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">
                        {{ s.owner?.name }} · {{ s.location }}
                    </p>
                    <p class="text-sm text-wz-fg">
                        {{ s.capacity }} · $ {{ Number(s.price_per_hour).toFixed(2) }}
                        {{
                            s.booking_mode === 'seat'
                                ? t('bookings.priceUnitSeatShort')
                                : t('bookings.priceUnitWholeShort')
                        }}
                    </p>
                    <Link :href="route('admin.workspaces.edit', s.id)">
                        <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                    </Link>
                </article>
            </div>

            <div class="wz-surface hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('bookings.workspace') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('admin.owner') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.locationText') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.capacity') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.pricePerHour') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in spaces.data" :key="s.id" class="border-t border-wz-border">
                            <td class="px-3 py-2 text-wz-fg">{{ s.id }}</td>
                            <td class="px-3 py-2 font-medium text-wz-fg">{{ s.name }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ s.owner?.name }}</td>
                            <td class="px-3 py-2 text-wz-fg-muted">{{ s.location }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ s.capacity }}</td>
                            <td class="px-3 py-2 text-wz-fg">
                                $ {{ Number(s.price_per_hour).toFixed(2) }}
                                {{
                                    s.booking_mode === 'seat'
                                        ? t('bookings.priceUnitSeatShort')
                                        : t('bookings.priceUnitWholeShort')
                                }}
                            </td>
                            <td class="px-3 py-2">
                                <Link :href="route('admin.workspaces.edit', s.id)">
                                    <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="spaces.links" />
    </AppLayout>
</template>
