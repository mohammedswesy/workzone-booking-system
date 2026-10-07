<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Badge from './Badge.vue';
import Button from './Button.vue';
import WorkspaceCover from './WorkspaceCover.vue';
import { formatDistanceKm } from '@/utils/mapPopup';

const props = defineProps({
    space: { type: Object, required: true },
});

const { t, locale } = useI18n();

/** Venue cards expose slug + from_price; legacy unit cards use id + price_per_hour. */
const isVenue = computed(() => Boolean(props.space?.slug) && (props.space?.from_price != null || props.space?.unit_count != null));

const placeLabel = computed(() => {
    const s = props.space || {};
    return s.place?.city || s.place?.name || s.location || s.address || '—';
});

const amenities = computed(() => {
    const list = props.space?.amenities;
    return Array.isArray(list) ? list.filter((a) => a && a.id).slice(0, 3) : [];
});

const discount = computed(() => Number(props.space?.active_discount_percent ?? 0));
const price = computed(() => Number(
    isVenue.value
        ? (props.space?.from_price ?? 0)
        : (props.space?.price_per_hour ?? 0),
));
const effective = computed(() => Number(
    isVenue.value
        ? price.value
        : (props.space?.effective_price_per_hour ?? price.value),
));

const href = computed(() => {
    if (!props.space) return '#';
    if (props.space.slug) {
        return route('spaces.show', props.space.slug);
    }
    return route('spaces.show', props.space.id);
});

function typeLabel(type) {
    return t(`venues.types.${type}`, type);
}

const openBadgeLabel = computed(() => {
    const b = props.space?.open_badge;
    if (!b?.label_key) return '';
    const key = b.label_key === 'open_now' ? 'openNow' : b.label_key;
    return t(`availability.${key}`, b.replace || {});
});

const distanceLabel = computed(() => {
    const km = props.space?.distance_km;
    if (km == null || km === '') return '';
    const n = formatDistanceKm(km, locale.value);
    if (!n) return '';
    return t('nearMe.distanceKm', { distance: n });
});
</script>

<template>
    <article class="wz-surface flex h-full flex-col overflow-hidden" data-testid="workspace-card">
        <div class="relative">
            <WorkspaceCover :space="space || {}" img-class="h-44 w-full object-cover" />
            <div class="absolute start-3 top-3 flex flex-col gap-1">
                <Badge v-if="space?.featured" tone="accent">{{ t('spaces.featured') }}</Badge>
                <Badge
                    v-if="space?.open_badge?.status"
                    :tone="space.open_badge.status === 'open' ? 'success' : 'neutral'"
                >
                    {{ openBadgeLabel }}
                </Badge>
            </div>
            <div class="absolute end-3 top-3">
                <Badge v-if="discount > 0" tone="danger">
                    {{ space.offer_label || t('spaces.discount', { n: discount }) }}
                </Badge>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div>
                <h3 class="font-display text-lg font-semibold text-wz-fg">{{ space?.name || '—' }}</h3>
                <p class="mt-1 text-sm text-wz-fg-muted">{{ placeLabel }}</p>
                <p
                    v-if="distanceLabel"
                    class="mt-1 text-sm font-medium text-wz-brand"
                    data-testid="distance-km"
                >
                    {{ distanceLabel }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs text-wz-fg-muted">
                <span v-if="isVenue && space?.unit_count != null" class="rounded-lg bg-wz-muted px-2 py-1">
                    {{ t('venues.unitCount', { n: space.unit_count }) }}
                </span>
                <span v-else-if="!isVenue" class="rounded-lg bg-wz-muted px-2 py-1">
                    {{ t('spaces.capacity', { n: space?.capacity ?? 0 }) }}
                </span>
                <span
                    v-for="type in (space?.unit_types || []).slice(0, 3)"
                    :key="type"
                    class="rounded-lg bg-wz-muted px-2 py-1"
                >
                    {{ typeLabel(type) }}
                </span>
                <span
                    v-for="a in amenities"
                    :key="a.id"
                    class="rounded-lg bg-wz-muted px-2 py-1"
                >
                    {{ a.name }}
                </span>
            </div>

            <div class="mt-auto flex items-end justify-between gap-3 pt-2">
                <div>
                    <p class="text-xs text-wz-fg-muted">
                        <template v-if="isVenue">{{ t('venues.fromPrice') }}</template>
                        <template v-else>
                            {{
                                space?.booking_mode === 'seat'
                                    ? t('spaces.priceUnitSeat')
                                    : t('spaces.priceUnitWhole')
                            }}
                        </template>
                    </p>
                    <template v-if="!isVenue && discount > 0">
                        <div class="text-xs text-wz-fg-muted line-through">
                            ${{ Number(space?.price_per_hour ?? 0).toFixed(2) }}
                        </div>
                        <div class="text-lg font-semibold text-wz-brand">
                            ${{ effective.toFixed(2) }}
                        </div>
                    </template>
                    <template v-else>
                        <div class="text-lg font-semibold text-wz-fg">
                            ${{ price.toFixed(2) }}
                        </div>
                    </template>
                </div>

                <div v-if="space?.id || space?.slug" class="flex gap-2">
                    <Link :href="href">
                        <Button size="sm" variant="secondary">{{ t('spaces.details') }}</Button>
                    </Link>
                    <Link v-if="!isVenue && space?.id" :href="route('user.bookings.create', { workspace_id: space.id })">
                        <Button size="sm">{{ t('spaces.book') }}</Button>
                    </Link>
                    <Link v-else :href="href">
                        <Button size="sm">{{ t('spaces.book') }}</Button>
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>
