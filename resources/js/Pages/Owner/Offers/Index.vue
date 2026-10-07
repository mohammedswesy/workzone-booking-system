<script setup>
import { computed, reactive, ref, watch } from 'vue';
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
    offers: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', per_page: 12 }) },
});

const { t, locale } = useI18n();
const deleteTarget = ref(null);

const form = reactive({
    search: props.filters?.search ?? '',
    per_page: props.filters?.per_page ?? 12,
});

function apply() {
    router.get(route('owner.offers.index'), { ...form }, { preserveState: true, replace: true });
}

watch(() => form.per_page, apply);

const hasData = computed(() => props.offers?.data?.length);

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString(locale.value === 'ar' ? 'ar' : 'en');
    } catch {
        return value;
    }
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    router.delete(route('owner.offers.destroy', deleteTarget.value), {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :title="t('owner.offersTitle')">
        <Head :title="t('owner.offersTitle')" />

        <PageHeader :title="t('owner.offersTitle')" :subtitle="t('owner.offersSubtitle')">
            <template #actions>
                <Link :href="route('owner.offers.create')">
                    <Button variant="primary">{{ t('owner.addOffer') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <Input
                    id="offer-search"
                    v-model="form.search"
                    :placeholder="t('common.search')"
                    @keyup.enter="apply"
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
            <Button variant="secondary" @click="apply">{{ t('owner.apply') }}</Button>
        </div>

        <EmptyState
            v-if="!hasData"
            :title="t('common.empty')"
            :description="t('owner.offersSubtitle')"
        >
            <template #action>
                <Link :href="route('owner.offers.create')">
                    <Button size="sm" variant="primary">{{ t('owner.addOffer') }}</Button>
                </Link>
            </template>
        </EmptyState>

        <template v-else>
            <!-- Mobile cards -->
            <div class="space-y-3 md:hidden">
                <article v-for="o in offers.data" :key="o.id" class="wz-surface space-y-2 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-medium text-wz-fg">{{ o.title }}</h3>
                        <Badge :tone="o.is_active ? 'success' : 'neutral'">
                            {{ o.is_active ? t('owner.isActive') : t('owner.inactive') }}
                        </Badge>
                    </div>
                    <p class="text-sm text-wz-fg-muted">
                        {{ o.venue?.name || o.workspace?.name || '—' }} · {{ o.discount_percent }}%
                    </p>
                    <p class="text-sm text-wz-fg-muted">
                        {{ formatDate(o.starts_at) }} — {{ formatDate(o.ends_at) }}
                    </p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Link :href="route('owner.offers.edit', o.id)">
                            <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                        </Link>
                        <Button size="sm" variant="danger" @click="deleteTarget = o.id">
                            {{ t('common.delete') }}
                        </Button>
                    </div>
                </article>
            </div>

            <!-- Desktop table -->
            <div class="wz-surface hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="bg-wz-muted text-wz-fg-muted">
                        <tr>
                            <th class="px-3 py-2 text-start">#</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.offerTitle') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.offerVenue') }} / {{ t('owner.offerUnit') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.discount') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.period') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('owner.status') }}</th>
                            <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="o in offers.data" :key="o.id" class="border-t border-wz-border">
                            <td class="px-3 py-2 text-wz-fg">{{ o.id }}</td>
                            <td class="px-3 py-2 font-medium text-wz-fg">{{ o.title }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ o.venue?.name || o.workspace?.name || '—' }}</td>
                            <td class="px-3 py-2 text-wz-fg">{{ o.discount_percent }}%</td>
                            <td class="px-3 py-2 text-wz-fg-muted">
                                {{ formatDate(o.starts_at) }} — {{ formatDate(o.ends_at) }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge :tone="o.is_active ? 'success' : 'neutral'">
                                    {{ o.is_active ? t('owner.isActive') : t('owner.inactive') }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <Link :href="route('owner.offers.edit', o.id)">
                                        <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                                    </Link>
                                    <Button size="sm" variant="danger" @click="deleteTarget = o.id">
                                        {{ t('common.delete') }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <Pagination v-if="offers?.links" :links="offers.links" />

        <ConfirmDialog
            :show="Boolean(deleteTarget)"
            :title="t('owner.deleteOfferTitle')"
            :message="t('owner.deleteOfferMessage')"
            :confirm-label="t('common.delete')"
            danger
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AppLayout>
</template>
