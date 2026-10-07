<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import VenueFormFields from '@/Components/Venues/VenueFormFields.vue';
import GalleryManager from '@/Components/Ui/GalleryManager.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    checklist: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    cityCenters: { type: Object, default: () => ({}) },
    hours_config_needed: { type: Boolean, default: false },
});

const { t } = useI18n();
const form = useForm({
    name: props.venue.name,
    slug: props.venue.slug,
    description: props.venue.description || '',
    location_id: props.venue.location_id,
    address: props.venue.address || '',
    lat: props.venue.place?.lat ?? null,
    lng: props.venue.place?.lng ?? null,
    timezone: props.venue.timezone || 'Asia/Gaza',
    status: props.venue.status?.value || props.venue.status,
    featured: Boolean(props.venue.featured),
    amenities: (props.venue.amenities || []).map((a) => a.id),
});

function save() {
    form.put(route('owner.venues.update', props.venue.slug));
}

function publish() {
    form.status = 'published';
    save();
}

function archiveUnit(unit) {
    if (!confirm('Archive this unit?')) return;
    router.delete(route('owner.venues.units.destroy', [props.venue.slug, unit.id]));
}
</script>

<template>
    <AppLayout :title="venue.name">
        <Head :title="venue.name" />
        <PageHeader :title="venue.name" :subtitle="t('venues.showTitle')">
            <template #actions>
                <Link :href="route('owner.venues.availability.edit', venue.slug)">
                    <Button variant="secondary">{{ t('venues.manageHours') }}</Button>
                </Link>
                <Link :href="route('owner.venues.units.create', venue.slug)">
                    <Button>{{ t('venues.addUnit') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="hours_config_needed"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="alert"
            data-testid="hours-config-banner"
        >
            {{ t('availability.hoursNotConfigured') }}
            <Link :href="route('owner.venues.availability.edit', venue.slug)" class="ms-2 font-medium text-wz-brand underline-offset-2 hover:underline">
                {{ t('venues.manageHours') }}
            </Link>
        </div>

        <div
            v-if="checklist.published_missing_location"
            class="mb-6 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="alert"
            data-testid="published-missing-location"
        >
            {{ t('venues.publishedMissingLocation') }}
        </div>

        <div class="mb-6 wz-surface p-4" data-testid="publish-checklist">
            <h2 class="font-semibold">{{ t('venues.checklistTitle') }}</h2>
            <ul class="mt-2 space-y-1 text-sm">
                <li :class="checklist.has_unit ? 'text-wz-success' : 'text-wz-fg-muted'">
                    {{ checklist.has_unit ? '✓' : '○' }} {{ t('venues.checklistUnit') }}
                </li>
                <li :class="checklist.has_published_unit ? 'text-wz-success' : 'text-wz-fg-muted'">
                    {{ checklist.has_published_unit ? '✓' : '○' }} {{ t('venues.checklistPublishedUnit') }}
                </li>
                <li :class="checklist.has_coordinates ? 'text-wz-success' : 'text-wz-fg-muted'">
                    {{ checklist.has_coordinates ? '✓' : '○' }} {{ t('venues.checklistLocation') }}
                </li>
            </ul>
            <p class="mt-2 text-xs text-wz-fg-muted">{{ t('venues.checklistLocationHint') }}</p>
            <Button
                v-if="checklist.can_publish && !checklist.is_published"
                class="mt-3"
                type="button"
                data-testid="publish-venue"
                @click="publish"
            >
                {{ t('venues.checklistReady') }}
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <form class="wz-surface space-y-4 p-5" @submit.prevent="save">
                <VenueFormFields
                    :form="form"
                    :locations="locations"
                    :amenities="amenities"
                    :city-centers="cityCenters"
                    id-prefix="owner-venue-edit"
                />
                <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
            </form>

            <div class="wz-surface p-5 lg:col-span-2">
                <GalleryManager
                    :venue-slug="venue.slug"
                    :images="venue.images || []"
                    route-prefix="owner"
                />
            </div>

            <div class="wz-surface p-5">
                <h2 class="mb-3 font-semibold">{{ t('venues.unitsTitle') }}</h2>
                <div v-if="!venue.units?.length" class="text-sm text-wz-fg-muted">{{ t('common.empty') }}</div>
                <ul class="space-y-3">
                    <li
                        v-for="unit in venue.units || []"
                        :key="unit.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-wz-border px-3 py-2"
                    >
                        <div>
                            <p class="font-medium">{{ unit.name }}</p>
                            <p class="text-xs text-wz-fg-muted">
                                {{ unit.type }} · {{ unit.booking_mode }} · ${{ Number(unit.price_per_hour).toFixed(2) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge tone="neutral">{{ unit.status }}</Badge>
                            <Link :href="route('owner.venues.units.edit', [venue.slug, unit.id])">
                                <Button size="sm" variant="secondary">{{ t('common.edit') }}</Button>
                            </Link>
                            <Button size="sm" variant="ghost" @click="archiveUnit(unit)">{{ t('common.delete') }}</Button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
