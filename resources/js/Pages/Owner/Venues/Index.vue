<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import Badge from '@/Components/Ui/Badge.vue';

defineProps({
    venues: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    missing_coordinates_count: { type: Number, default: 0 },
});

const { t } = useI18n();
</script>

<template>
    <AppLayout :title="t('venues.indexTitle')">
        <Head :title="t('venues.indexTitle')" />
        <PageHeader :title="t('venues.indexTitle')" :subtitle="t('venues.indexSubtitle')">
            <template #actions>
                <Link :href="route('owner.venues.create')">
                    <Button>{{ t('venues.createTitle') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="missing_coordinates_count > 0"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="status"
            data-testid="owner-missing-location-banner"
        >
            <p class="font-semibold">{{ t('venues.locationMissingBanner') }}</p>
            <p class="mt-1 text-wz-fg-muted">{{ t('venues.locationMissingBannerHint') }}</p>
        </div>

        <EmptyState v-if="!venues.data?.length" :title="t('common.empty')" :hint="t('venues.createHint')" />
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="v in venues.data"
                :key="v.id"
                :href="route('owner.venues.show', v.slug)"
                class="wz-surface block overflow-hidden p-4 hover:border-wz-brand"
            >
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-display text-lg font-semibold">{{ v.name }}</h3>
                    <div class="flex flex-col items-end gap-1">
                        <Badge :tone="v.status === 'published' ? 'brand' : 'neutral'">{{ v.status }}</Badge>
                        <Badge v-if="!v.has_coordinates" tone="warning" data-testid="location-missing-badge">
                            {{ t('venues.locationMissingBadge') }}
                        </Badge>
                    </div>
                </div>
                <p class="mt-1 text-sm text-wz-fg-muted">{{ v.place?.city || v.address || '—' }}</p>
                <p class="mt-3 text-sm">{{ t('venues.unitCount', { n: v.units_count ?? 0 }) }}</p>
            </Link>
        </div>
        <Pagination :links="venues.links" />
    </AppLayout>
</template>
