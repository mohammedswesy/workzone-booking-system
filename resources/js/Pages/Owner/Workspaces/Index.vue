<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    spaces: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', per_page: 9 }) },
});

const { t } = useI18n();
const search = ref(props.filters?.search ?? '');
const perPage = ref(props.filters?.per_page ?? 9);
const deleteTarget = ref(null);

let debounce;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 400);
});

function applyFilters() {
    router.get(
        route('owner.workspaces.index'),
        {
            search: search.value || undefined,
            per_page: perPage.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    router.delete(route('owner.workspaces.destroy', deleteTarget.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}

function cover(space) {
    return (
        space.images?.[0]?.url ||
        space.image_url ||
        'https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=800&auto=format&fit=crop'
    );
}

function statusLabel(status) {
    return t(`owner.${status}`, status);
}
</script>

<template>
    <AppLayout :title="t('owner.workspacesTitle')">
        <Head :title="t('owner.workspacesTitle')" />

        <PageHeader
            :title="t('owner.workspacesTitle')"
            :subtitle="t('owner.workspacesSubtitle')"
        >
            <template #actions>
                <Link :href="route('owner.workspaces.create')">
                    <Button variant="primary">{{ t('owner.addWorkspace') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Input
                    id="owner-space-search"
                    v-model="search"
                    :placeholder="t('owner.searchWorkspaces')"
                >
                    <template #label>{{ t('common.search') }}</template>
                </Input>
            </div>
            <select
                v-model.number="perPage"
                class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg"
                @change="applyFilters"
            >
                <option :value="6">6</option>
                <option :value="9">9</option>
                <option :value="12">12</option>
                <option :value="15">15</option>
            </select>
        </div>

        <EmptyState
            v-if="!spaces?.data?.length"
            :title="t('common.empty')"
            :description="t('owner.workspacesSubtitle')"
        >
            <template #action>
                <Link :href="route('owner.workspaces.create')">
                    <Button size="sm" variant="primary">{{ t('owner.addWorkspace') }}</Button>
                </Link>
            </template>
        </EmptyState>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="s in spaces.data"
                :key="s.id"
                class="wz-surface flex flex-col overflow-hidden"
            >
                <img :src="cover(s)" :alt="s.name" class="h-36 w-full object-cover" />
                <div class="flex flex-1 flex-col gap-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-display text-lg font-semibold text-wz-fg">{{ s.name }}</h3>
                        <Badge :tone="s.status === 'published' ? 'success' : 'neutral'">
                            {{ statusLabel(s.status) }}
                        </Badge>
                        <Badge v-if="s.featured" tone="accent">{{ t('owner.featured') }}</Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">{{ s.location }}</p>
                    <p class="text-sm text-wz-fg">
                        {{ t('owner.capacity') }}: {{ s.capacity }}
                        <span class="mx-1 text-wz-border">·</span>
                        $ {{ Number(s.price_per_hour).toFixed(2) }}/h
                    </p>
                    <div class="mt-auto flex flex-wrap gap-2 pt-2">
                        <Link :href="route('owner.workspaces.edit', s.id)">
                            <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                        </Link>
                        <Button size="sm" variant="danger" @click="deleteTarget = s.id">
                            {{ t('common.delete') }}
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <Pagination v-if="spaces?.links" :links="spaces.links" />

        <ConfirmDialog
            :show="Boolean(deleteTarget)"
            :title="t('owner.deleteWorkspaceTitle')"
            :message="t('owner.deleteWorkspaceMessage')"
            :confirm-label="t('common.delete')"
            danger
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AppLayout>
</template>
