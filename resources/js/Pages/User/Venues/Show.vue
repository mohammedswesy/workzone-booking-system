<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import WorkspaceCover from '@/Components/Ui/WorkspaceCover.vue';
import VenueLightbox from '@/Components/Ui/VenueLightbox.vue';
import LeafletMap from '@/Components/Map/LeafletMap.vue';
import { workspaceCoverUrl } from '@/utils/workspaceImage';

const props = defineProps({
    venue: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    workspace: { type: Object, required: true },
    selected_unit_id: { type: Number, default: null },
    can_book: { type: Boolean, default: false },
    pending_booking_id: { type: Number, default: null },
    availability: { type: Object, default: null },
    map: { type: Object, default: null },
});

const { t } = useI18n();
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

const selected = computed(() => props.workspace);

const badgeLabel = computed(() => {
    const b = props.availability?.badge;
    if (!b) return '';
    return t(`availability.${b.label_key === 'open_now' ? 'openNow' : b.label_key}`, b.replace || {});
});

const venueGallery = computed(() => {
    const list = props.venue.images || [];
    if (list.length) return list;
    const cover = workspaceCoverUrl(props.venue);
    return cover ? [{ id: 'venue-cover', url: cover }] : [];
});

const unitGallery = computed(() => {
    const list = selected.value?.images || [];
    if (list.length) return list;
    return venueGallery.value;
});

const amenities = computed(() => {
    const unit = selected.value?.amenities || [];
    if (unit.length) return unit;
    return props.venue.amenities || [];
});

const placeLabel = computed(() => {
    const p = props.venue.place;
    if (!p) return props.venue.address || '';
    return [p.name, p.city, props.venue.address || p.address].filter(Boolean).join(' · ');
});

function typeLabel(type) {
    const key = typeof type === 'object' ? type?.value : type;
    return key ? t(`venues.types.${key}`, key) : t('venues.types.other');
}

function selectUnit(unit) {
    if (!unit?.id || unit.id === props.selected_unit_id) return;
    router.get(
        route('spaces.show', props.venue.slug),
        { unit: unit.id },
        { preserveScroll: true, replace: true },
    );
}

function book() {
    router.visit(route('user.bookings.create', { workspace_id: selected.value.id }));
}

function openLightbox(i = 0) {
    lightboxIndex.value = i;
    lightboxOpen.value = true;
}

function gallerySrc(img) {
    return img?.gallery_url || img?.url || '';
}

function thumbSrc(img) {
    return img?.card_url || img?.url || '';
}
</script>

<template>
    <AppLayout :title="venue.name">
        <Head :title="venue.name" />

        <PageHeader :title="venue.name">
            <template #subtitle>
                {{ placeLabel }}
            </template>
            <template #actions>
                <Badge v-if="venue.featured" tone="accent">{{ t('spaces.featured') }}</Badge>
                <Badge
                    v-if="availability?.badge"
                    :tone="availability.badge.status === 'open' ? 'success' : 'neutral'"
                >
                    {{ badgeLabel }}
                </Badge>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-6">
                <div class="wz-surface overflow-hidden" data-testid="venue-gallery">
                    <button
                        type="button"
                        class="block w-full"
                        data-testid="gallery-main"
                        @click="openLightbox(0)"
                    >
                        <WorkspaceCover
                            :space="unitGallery[0]
                                ? { ...selected, images: unitGallery, cover_image_url: gallerySrc(unitGallery[0]) }
                                : venue"
                            img-class="h-full w-full object-cover"
                        />
                    </button>
                    <div v-if="unitGallery.length > 1" class="grid grid-cols-4 gap-2 p-3">
                        <button
                            v-for="(img, i) in unitGallery.slice(0, 8)"
                            :key="img.id"
                            type="button"
                            class="aspect-[4/3] overflow-hidden rounded-lg"
                            data-testid="gallery-thumb"
                            @click="openLightbox(i)"
                        >
                            <img
                                :src="thumbSrc(img)"
                                class="h-full w-full object-cover"
                                loading="lazy"
                                :alt="img.caption || t('spaces.gallery')"
                            />
                        </button>
                    </div>
                </div>

                <VenueLightbox
                    v-if="lightboxOpen && unitGallery.length"
                    :images="unitGallery"
                    :start-index="lightboxIndex"
                    @close="lightboxOpen = false"
                />

                <section class="wz-surface p-5">
                    <h2 class="mb-2 font-semibold text-wz-fg">{{ t('spaces.about') }}</h2>
                    <p class="text-sm leading-relaxed text-wz-fg-muted">
                        {{ venue.description || t('spaces.subtitle') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Badge
                            v-for="a in amenities"
                            :key="a.id"
                            tone="brand"
                        >
                            {{ a.name }}
                        </Badge>
                    </div>
                </section>

                <section class="wz-surface p-5">
                    <h2 class="mb-3 font-semibold">{{ t('venues.unitsTitle') }}</h2>
                    <p class="mb-3 text-sm text-wz-fg-muted">{{ t('venues.selectUnit') }}</p>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="unit in units"
                            :key="unit.id"
                        >
                            <button
                                type="button"
                                class="w-full rounded-xl border px-3 py-3 text-start transition"
                                :class="unit.id === selected_unit_id
                                    ? 'border-wz-brand bg-wz-brand-soft'
                                    : 'border-wz-border hover:border-wz-brand/40'"
                                @click="selectUnit(unit)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-wz-fg">{{ unit.name }}</p>
                                        <p class="mt-1 text-xs text-wz-fg-muted">
                                            {{ typeLabel(unit.type) }}
                                            · {{ t('spaces.capacity', { n: unit.capacity }) }}
                                        </p>
                                    </div>
                                    <Badge v-if="(unit.active_discount_percent ?? 0) > 0" tone="danger">
                                        {{ t('spaces.discount', { n: unit.active_discount_percent }) }}
                                    </Badge>
                                </div>
                                <p class="mt-2 text-sm font-semibold text-wz-brand">
                                    ${{ Number(unit.effective_price_per_hour ?? unit.price_per_hour).toFixed(2) }}
                                    <span class="text-xs font-normal text-wz-fg-muted">/ hr</span>
                                </p>
                            </button>
                        </li>
                    </ul>
                </section>

                <section class="wz-surface p-5">
                    <h2 class="mb-3 font-semibold">{{ t('availability.weeklyHours') }}</h2>
                    <p class="mb-2 text-xs text-wz-fg-muted">{{ availability?.timezone }}</p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="day in (availability?.schedule || [])" :key="day.weekday">
                            <span class="inline-block w-24 font-medium">{{ t(`availability.days.${['sun','mon','tue','wed','thu','fri','sat'][day.weekday]}`) }}</span>
                            <span v-if="day.is_closed">{{ t('availability.closed') }}</span>
                            <span v-else>{{ day.opens_at }} – {{ day.closes_at }}</span>
                        </li>
                    </ul>
                </section>

                <section class="wz-surface p-5">
                    <h2 class="mb-3 font-semibold">{{ t('spaces.location') }}</h2>
                    <template v-if="map?.lat != null && map?.lng != null">
                        <LeafletMap
                            :lat="map.lat"
                            :lng="map.lng"
                            :tile-url="map.tile_url"
                            :attribution="map.tile_attribution"
                            :center="{ lat: map.lat, lng: map.lng, zoom: 15 }"
                            height-class="h-48"
                        />
                        <a
                            v-if="map.google_url"
                            :href="map.google_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-block text-sm text-wz-brand hover:underline"
                        >
                            {{ t('venues.openInMaps') }}
                        </a>
                    </template>
                    <p v-else class="text-sm text-wz-fg-muted">{{ t('venues.noCoordinates') }}</p>
                </section>
            </div>

            <aside class="wz-surface sticky top-24 h-fit space-y-4 p-5">
                <div>
                    <p class="text-sm font-medium text-wz-fg">{{ selected.name }}</p>
                    <div class="mt-1 text-sm text-wz-fg-muted">
                        {{
                            selected.booking_mode === 'seat'
                                ? t('spaces.priceUnitSeat')
                                : t('spaces.priceUnitWhole')
                        }}
                        · {{ typeLabel(selected.type) }}
                    </div>
                    <template v-if="(selected.active_discount_percent ?? 0) > 0">
                        <div class="mt-2 text-sm text-wz-fg-muted line-through">
                            ${{ Number(selected.price_per_hour).toFixed(2) }}
                        </div>
                        <div class="text-3xl font-semibold text-wz-brand">
                            ${{ Number(selected.effective_price_per_hour).toFixed(2) }}
                        </div>
                        <Badge tone="accent" class="mt-2">{{ t('spaces.offer') }}</Badge>
                    </template>
                    <template v-else>
                        <div class="mt-2 text-3xl font-semibold text-wz-fg">
                            ${{ Number(selected.price_per_hour).toFixed(2) }}
                        </div>
                    </template>
                </div>

                <p v-if="availability?.paused" class="text-xs text-wz-danger">
                    {{ availability.badge?.replace?.note || t('availability.pauseToggle') }}
                </p>
                <p class="text-xs text-wz-fg-muted">
                    {{ t('spaces.capacity', { n: selected.capacity }) }}
                </p>

                <div class="grid gap-2">
                    <Button v-if="can_book && !pending_booking_id" block size="lg" @click="book">
                        {{ t('spaces.bookCta') }}
                    </Button>
                    <Link v-else-if="pending_booking_id" :href="route('user.bookings.show', pending_booking_id)">
                        <Button block variant="secondary">{{ t('spaces.pendingBooking') }}</Button>
                    </Link>
                    <Link v-else :href="route('login')">
                        <Button block variant="secondary">{{ t('nav.login') }}</Button>
                    </Link>
                    <Link :href="route('spaces.index')">
                        <Button block variant="ghost">{{ t('spaces.backToSpaces') }}</Button>
                    </Link>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
