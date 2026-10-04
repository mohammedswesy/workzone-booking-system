<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';

defineProps({
    users: Object,
});

const { t, locale } = useI18n();

function roleTone(role) {
    if (role === 'admin') return 'danger';
    if (role === 'owner') return 'brand';
    return 'neutral';
}

function roleLabel(role) {
    const map = {
        user: 'admin.roleUser',
        owner: 'admin.roleOwner',
        admin: 'admin.roleAdmin',
    };
    return t(map[role] || role);
}

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en');
    } catch {
        return value;
    }
}
</script>

<template>
    <AppLayout :title="t('admin.usersTitle')">
        <Head :title="t('admin.usersTitle')" />

        <PageHeader :title="t('admin.usersTitle')" :subtitle="t('admin.usersSubtitle')" />

        <EmptyState
            v-if="!users?.data?.length"
            :title="t('common.empty')"
            :description="t('common.emptyHint')"
        />

        <template v-else>
            <div class="space-y-3 md:hidden">
                <article v-for="u in users.data" :key="u.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">{{ u.name }}</h3>
                        <Badge :tone="roleTone(u.role)">{{ roleLabel(u.role) }}</Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">{{ u.email }}</p>
                    <p class="text-xs text-wz-fg-muted">{{ formatDate(u.created_at) }}</p>
                    <Link :href="route('admin.users.edit', u.id)">
                        <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                    </Link>
                </article>
            </div>

            <div class="wz-surface hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('admin.name') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('admin.email') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.role') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('admin.joined') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users.data" :key="u.id" class="border-t border-wz-border">
                            <td class="px-3 py-2 text-wz-fg">{{ u.id }}</td>
                            <td class="px-3 py-2 font-medium text-wz-fg">{{ u.name }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ u.email }}</td>
                            <td class="px-3 py-2">
                                <Badge :tone="roleTone(u.role)">{{ roleLabel(u.role) }}</Badge>
                            </td>
                            <td class="px-3 py-2 text-wz-fg-muted">{{ formatDate(u.created_at) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="route('admin.users.edit', u.id)">
                                    <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="users.links" />
    </AppLayout>
</template>
