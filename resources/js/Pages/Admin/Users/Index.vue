<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import LoadingState from '@/Components/Ui/LoadingState.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    users: Object,
    filters: { type: Object, default: () => ({ role: '', search: '' }) },
    invitation: { type: Object, default: null },
});

const page = usePage();
const { t, locale } = useI18n();

const form = reactive({
    search: props.filters?.search || '',
    role: props.filters?.role || '',
});

const inviteBanner = computed(
    () => props.invitation || page.props.flash?.invitation || null,
);
const copied = ref(false);
const suspendTarget = ref(null);
const deleteTarget = ref(null);
const filterLoading = ref(false);

onMounted(() => {
    const onStart = () => {
        filterLoading.value = true;
    };
    const onStop = () => {
        filterLoading.value = false;
    };
    router.on('start', onStart);
    router.on('finish', onStop);
    router.on('error', onStop);
    onUnmounted(() => {
        router.off('start', onStart);
        router.off('finish', onStop);
        router.off('error', onStop);
    });
});

watch(
    () => form.role,
    () => apply(),
);

function apply() {
    router.get(
        route('admin.users.index'),
        {
            search: form.search || undefined,
            role: form.role || undefined,
        },
        { preserveState: true, replace: true },
    );
}

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

async function copyLink() {
    if (!inviteBanner.value?.setup_url) return;
    try {
        await navigator.clipboard.writeText(inviteBanner.value.setup_url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        /* ignore */
    }
}

function suspendUser() {
    if (!suspendTarget.value) return;
    router.post(route('admin.users.suspend', suspendTarget.value), {}, {
        preserveScroll: true,
        onFinish: () => {
            suspendTarget.value = null;
        },
    });
}

function reactivate(id) {
    router.post(route('admin.users.reactivate', id), {}, { preserveScroll: true });
}

function deleteUser() {
    if (!deleteTarget.value) return;
    router.delete(route('admin.users.destroy', deleteTarget.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :title="t('admin.usersTitle')">
        <Head :title="t('admin.usersTitle')" />

        <PageHeader :title="t('admin.usersTitle')" :subtitle="t('admin.usersSubtitle')">
            <template #actions>
                <Link :href="route('admin.users.create')">
                    <Button variant="primary">{{ t('admin.createUser') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="inviteBanner"
            class="mb-4 rounded-xl border border-wz-border bg-wz-brand-soft px-4 py-3 text-sm text-wz-fg"
        >
            <p v-if="inviteBanner.sent">
                {{ t('admin.inviteSent', { email: inviteBanner.email }) }}
            </p>
            <template v-else>
                <p class="font-medium">{{ t('admin.inviteLinkTitle') }}</p>
                <p class="mt-1 text-wz-fg-muted">{{ t('admin.inviteLinkHint') }}</p>
                <code class="mt-2 block break-all rounded-lg bg-wz-elevated px-3 py-2 text-xs">
                    {{ inviteBanner.setup_url }}
                </code>
                <Button class="mt-2" size="sm" variant="secondary" @click="copyLink">
                    {{ copied ? t('admin.copied') : t('admin.copyLink') }}
                </Button>
            </template>
        </div>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Input
                    id="admin-user-search"
                    v-model="form.search"
                    :placeholder="t('common.search')"
                    @keyup.enter="apply"
                >
                    <template #label>{{ t('common.search') }}</template>
                </Input>
            </div>
            <div class="w-full sm:w-48">
                <Select id="admin-user-role-filter" v-model="form.role">
                    <template #label>{{ t('admin.filterRole') }}</template>
                    <option value="">{{ t('status.all') }}</option>
                    <option value="user">{{ t('admin.roleUser') }}</option>
                    <option value="owner">{{ t('admin.roleOwner') }}</option>
                    <option value="admin">{{ t('admin.roleAdmin') }}</option>
                </Select>
            </div>
            <Button variant="secondary" @click="apply">{{ t('owner.apply') }}</Button>
        </div>

        <LoadingState v-if="filterLoading" />

        <EmptyState
            v-else-if="!users?.data?.length"
            :title="t('common.empty')"
            :description="t('common.emptyHint')"
        />

        <template v-else>
            <div class="space-y-3 md:hidden">
                <article v-for="u in users.data" :key="u.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">{{ u.name }}</h3>
                        <Badge :tone="roleTone(u.role)">{{ roleLabel(u.role) }}</Badge>
                        <Badge :tone="u.is_active ? 'success' : 'danger'">
                            {{ u.is_active ? t('admin.active') : t('admin.suspended') }}
                        </Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">{{ u.email }}</p>
                    <p v-if="u.phone" class="text-sm text-wz-fg-muted">{{ u.phone }}</p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Link :href="route('admin.users.edit', u.id)">
                            <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                        </Link>
                        <Button
                            v-if="u.is_active"
                            size="sm"
                            variant="danger"
                            @click="suspendTarget = u.id"
                        >
                            {{ t('admin.suspend') }}
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            variant="primary"
                            @click="reactivate(u.id)"
                        >
                            {{ t('admin.reactivate') }}
                        </Button>
                    </div>
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
                            <th class="px-3 py-2 text-start">{{ t('admin.active') }}</th>
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
                            <td class="px-3 py-2">
                                <Badge :tone="u.is_active ? 'success' : 'danger'">
                                    {{ u.is_active ? t('admin.active') : t('admin.suspended') }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2 text-wz-fg-muted">{{ formatDate(u.created_at) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <Link :href="route('admin.users.edit', u.id)">
                                        <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                                    </Link>
                                    <Button
                                        v-if="u.is_active"
                                        size="sm"
                                        variant="danger"
                                        @click="suspendTarget = u.id"
                                    >
                                        {{ t('admin.suspend') }}
                                    </Button>
                                    <Button
                                        v-else
                                        size="sm"
                                        variant="primary"
                                        @click="reactivate(u.id)"
                                    >
                                        {{ t('admin.reactivate') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="deleteTarget = u.id"
                                    >
                                        {{ t('common.delete') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination :links="users.links" />

        <ConfirmDialog
            :show="Boolean(suspendTarget)"
            :title="t('admin.suspendTitle')"
            :message="t('admin.suspendMessage')"
            :confirm-label="t('admin.suspend')"
            danger
            @confirm="suspendUser"
            @cancel="suspendTarget = null"
        />

        <ConfirmDialog
            :show="Boolean(deleteTarget)"
            :title="t('admin.deleteUserTitle')"
            :message="t('admin.deleteUserMessage')"
            :confirm-label="t('common.delete')"
            danger
            @confirm="deleteUser"
            @cancel="deleteTarget = null"
        />
    </AppLayout>
</template>
