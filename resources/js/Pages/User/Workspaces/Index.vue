<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import WorkspaceCard from '@/Components/Ui/WorkspaceCard.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import LoadingState from '@/Components/Ui/LoadingState.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import LeafletMap from '@/Components/Map/LeafletMap.vue';
import { useNavigationLoading } from '@/Composables/useNavigationLoading';
import { parseCoordinatePaste } from '@/utils/coordinates';
import { mapGeolocationError, requestUserLocation, roundNearMeCoord } from '@/utils/geolocation';

const props = defineProps({
    venues: { type: Object, default: null },
    spaces: { type: Object, default: null },
    filters: Object,
    near_me: { type: Object, default: () => ({}) },
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    unitTypes: { type: Array, default: () => [] },
    map: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const drawerOpen = ref(false);
const { loading: filterLoading } = useNavigationLoading();

const form = reactive({
    search: props.filters?.search || '',
    city: props.filters?.city || '',
    min_price: props.filters?.min_price || '',
    max_price: props.filters?.max_price || '',
    capacity: props.filters?.capacity || '',
    type: props.filters?.type || '',
    featured: Boolean(props.filters?.featured),
    open_now: Boolean(props.filters?.open_now),
    amenities: String(props.filters?.amenities || '')
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean),
    near_lat: props.filters?.near_lat != null && props.filters?.near_lat !== ''
        ? String(props.filters.near_lat)
        : '',
    near_lng: props.filters?.near_lng != null && props.filters?.near_lng !== ''
        ? String(props.filters.near_lng)
        : '',
    radius_km: props.filters?.radius_km != null && props.filters?.radius_km !== ''
        ? String(props.filters.radius_km)
        : 'any',
});

const viewMode = ref('list');
const mapMarkers = ref([]);
const mapCfg = computed(() => props.map || {});
const pickOnMap = ref(false);
const geoMessage = ref('');
const pasteHint = ref('');
const geoBusy = ref(false);

const catalog = computed(() => props.venues || props.spaces);
const items = computed(() => catalog.value?.data ?? []);

const userLocation = computed(() => {
    const lat = roundNearMeCoord(form.near_lat);
    const lng = roundNearMeCoord(form.near_lng);
    if (lat == null || lng == null) return null;
    return { lat, lng };
});

const nearMeActive = computed(() => Boolean(props.near_me?.active));
const excludedCount = computed(() => Number(props.near_me?.excluded_without_coords || 0));

function filterParams() {
    const lat = roundNearMeCoord(form.near_lat);
    const lng = roundNearMeCoord(form.near_lng);
    return {
        search: form.search || undefined,
        city: form.city || undefined,
        min_price: form.min_price || undefined,
        max_price: form.max_price || undefined,
        capacity: form.capacity || undefined,
        type: form.type || undefined,
        featured: form.featured ? 1 : undefined,
        open_now: form.open_now ? 1 : undefined,
        amenities: form.amenities.length ? form.amenities.join(',') : undefined,
        near_lat: lat != null && lng != null ? lat : undefined,
        near_lng: lat != null && lng != null ? lng : undefined,
        radius_km: lat != null && lng != null && form.radius_km !== 'any'
            ? form.radius_km
            : undefined,
    };
}

function apply() {
    router.get(route('spaces.index'), filterParams(), { preserveState: true, replace: true });
    drawerOpen.value = false;
    if (viewMode.value === 'map') {
        loadMarkers();
    }
}

function reset() {
    form.search = '';
    form.city = '';
    form.min_price = '';
    form.max_price = '';
    form.capacity = '';
    form.type = '';
    form.featured = false;
    form.open_now = false;
    form.amenities = [];
    clearNearMe();
    apply();
}

function clearNearMe() {
    form.near_lat = '';
    form.near_lng = '';
    form.radius_km = 'any';
    pickOnMap.value = false;
    geoMessage.value = '';
    pasteHint.value = '';
}

function clearNearMeAndApply() {
    clearNearMe();
    apply();
}

async function loadMarkers() {
    try {
        const { data } = await axios.get(route('spaces.map'), { params: filterParams() });
        mapMarkers.value = data.markers || [];
    } catch {
        mapMarkers.value = [];
    }
}

watch(viewMode, (mode) => {
    if (mode === 'map') loadMarkers();
});

function toggleAmenity(id) {
    const key = String(id);
    if (form.amenities.includes(key)) {
        form.amenities = form.amenities.filter((a) => a !== key);
    } else {
        form.amenities.push(key);
    }
}

function typeLabel(type) {
    return t(`venues.types.${type}`, type);
}

function applyPaste(field, event) {
    const text = event.clipboardData?.getData('text') ?? '';
    const parsed = parseCoordinatePaste(text);

    if (parsed.kind === 'pair') {
        event.preventDefault();
        form.near_lat = String(roundNearMeCoord(parsed.lat));
        form.near_lng = String(roundNearMeCoord(parsed.lng));
        pasteHint.value = '';
        return;
    }

    if (parsed.kind === 'url_unparsed') {
        event.preventDefault();
        pasteHint.value = t('venues.pasteCoordsHint');
        return;
    }

    if (parsed.kind === 'single') {
        event.preventDefault();
        const rounded = roundNearMeCoord(parsed.value);
        const v = rounded == null ? '' : String(rounded);
        if (field === 'lat') form.near_lat = v;
        else form.near_lng = v;
        pasteHint.value = '';
    }
}

async function useMyLocation() {
    geoBusy.value = true;
    geoMessage.value = '';
    try {
        const pos = await requestUserLocation();
        form.near_lat = String(pos.lat);
        form.near_lng = String(pos.lng);
        geoMessage.value = '';
        apply();
    } catch (err) {
        const code = mapGeolocationError(err);
        geoMessage.value = t(`nearMe.geo.${code}`);
    } finally {
        geoBusy.value = false;
    }
}

function onUserLocationPick(coords) {
    if (!coords) return;
    form.near_lat = String(coords.lat);
    form.near_lng = String(coords.lng);
    pickOnMap.value = false;
    apply();
}
</script>

<template>
    <AppLayout :title="t('spaces.title')">
        <Head :title="t('spaces.title')" />

        <PageHeader :title="t('spaces.title')" :subtitle="t('spaces.subtitle')">
            <template #actions>
                <div class="flex gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        :class="viewMode === 'list' ? 'ring-2 ring-wz-brand' : ''"
                        @click="viewMode = 'list'"
                    >
                        {{ t('availability.listView') }}
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        :class="viewMode === 'map' ? 'ring-2 ring-wz-brand' : ''"
                        @click="viewMode = 'map'"
                    >
                        {{ t('availability.mapView') }}
                    </Button>
                    <Button class="md:hidden" variant="secondary" @click="drawerOpen = true">
                        {{ t('spaces.filters') }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="wz-surface hidden h-fit p-4 md:block" data-testid="spaces-filters">
                <h2 class="mb-3 text-sm font-semibold text-wz-fg">{{ t('spaces.filters') }}</h2>
                <form class="grid gap-3" @submit.prevent="apply">
                    <Input v-model="form.search" :placeholder="t('spaces.search')" />
                    <Input v-model="form.city" :placeholder="t('spaces.city')" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="form.min_price" type="number" :placeholder="t('spaces.minPrice')" />
                        <Input v-model="form.max_price" type="number" :placeholder="t('spaces.maxPrice')" />
                    </div>
                    <Input v-model="form.capacity" type="number" :placeholder="t('spaces.capacity', { n: '' }).trim()" />
                    <label class="grid gap-1.5 text-sm">
                        <span class="font-medium text-wz-fg">{{ t('venues.filterType') }}</span>
                        <select
                            v-model="form.type"
                            class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm"
                        >
                            <option value="">{{ t('common.optional') }}</option>
                            <option v-for="type in unitTypes" :key="type" :value="type">
                                {{ typeLabel(type) }}
                            </option>
                        </select>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-wz-fg">
                        <input v-model="form.featured" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('spaces.onlyFeatured') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-wz-fg">
                        <input v-model="form.open_now" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('availability.openNowFilter') }}
                    </label>
                    <div v-if="amenities.length" class="grid gap-2">
                        <p class="text-xs font-medium text-wz-fg-muted">{{ t('spaces.amenities') }}</p>
                        <label
                            v-for="a in amenities"
                            :key="a.id"
                            class="flex items-center gap-2 text-sm text-wz-fg"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-wz-border text-wz-brand"
                                :checked="form.amenities.includes(String(a.id))"
                                @change="toggleAmenity(a.id)"
                            />
                            {{ a.name }}
                        </label>
                    </div>

                    <!-- Near me -->
                    <div class="mt-1 grid gap-2 rounded-xl border border-wz-border bg-wz-muted/40 p-3" data-testid="near-me-panel">
                        <p class="text-sm font-semibold text-wz-fg">{{ t('nearMe.title') }}</p>
                        <p class="text-xs text-wz-fg-muted">{{ t('nearMe.hint') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-1">
                                <label class="text-xs font-medium text-wz-fg" for="near-lat">{{ t('venues.latitude') }}</label>
                                <input
                                    id="near-lat"
                                    data-testid="near-lat"
                                    dir="ltr"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm"
                                    :value="form.near_lat"
                                    @input="form.near_lat = $event.target.value"
                                    @paste="applyPaste('lat', $event)"
                                />
                            </div>
                            <div class="grid gap-1">
                                <label class="text-xs font-medium text-wz-fg" for="near-lng">{{ t('venues.longitude') }}</label>
                                <input
                                    id="near-lng"
                                    data-testid="near-lng"
                                    dir="ltr"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm"
                                    :value="form.near_lng"
                                    @input="form.near_lng = $event.target.value"
                                    @paste="applyPaste('lng', $event)"
                                />
                            </div>
                        </div>
                        <p v-if="pasteHint" class="text-xs text-wz-warning">{{ pasteHint }}</p>
                        <label class="grid gap-1 text-xs">
                            <span class="font-medium text-wz-fg">{{ t('nearMe.radius') }}</span>
                            <select
                                v-model="form.radius_km"
                                data-testid="near-radius"
                                class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm"
                            >
                                <option value="any">{{ t('nearMe.radiusAny') }}</option>
                                <option value="5">5 km</option>
                                <option value="10">10 km</option>
                                <option value="25">25 km</option>
                                <option value="50">50 km</option>
                                <option value="100">100 km</option>
                            </select>
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                data-testid="use-my-location"
                                :disabled="geoBusy"
                                @click="useMyLocation"
                            >
                                {{ t('nearMe.useMyLocation') }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                data-testid="pick-on-map"
                                :class="pickOnMap ? 'ring-2 ring-wz-brand' : ''"
                                @click="pickOnMap = !pickOnMap; if (pickOnMap) viewMode = 'map'"
                            >
                                {{ t('nearMe.pickOnMap') }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                data-testid="clear-near-me"
                                @click="clearNearMeAndApply"
                            >
                                {{ t('nearMe.clear') }}
                            </Button>
                        </div>
                        <p v-if="geoMessage" class="text-xs text-wz-danger" data-testid="geo-message">{{ geoMessage }}</p>
                        <p v-if="pickOnMap" class="text-xs text-wz-brand">{{ t('nearMe.pickHint') }}</p>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <Button type="submit" class="flex-1" data-testid="apply-filters">{{ t('spaces.apply') }}</Button>
                        <Button type="button" variant="secondary" @click="reset">{{ t('spaces.reset') }}</Button>
                    </div>
                </form>
            </aside>

            <div class="min-w-0">
                <p
                    v-if="nearMeActive && excludedCount > 0"
                    class="mb-3 text-sm text-wz-fg-muted"
                    data-testid="near-me-excluded"
                >
                    {{ t('nearMe.excludedNote', { n: excludedCount }) }}
                </p>

                <LoadingState v-if="filterLoading" />
                <template v-else-if="viewMode === 'map'">
                    <p v-if="pickOnMap" class="mb-2 text-sm text-wz-brand">{{ t('nearMe.pickHint') }}</p>
                    <LeafletMap
                        :tile-url="mapCfg.tile_url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'"
                        :attribution="mapCfg.tile_attribution || ''"
                        :center="mapCfg.default_center || { lat: 31.5, lng: 34.46, zoom: 11 }"
                        :markers="mapMarkers"
                        :user-location="userLocation"
                        :pick-user-location="pickOnMap"
                        height-class="h-[28rem]"
                        @update:user-location="onUserLocationPick"
                    />
                    <p v-if="!mapMarkers.length" class="mt-3 text-sm text-wz-fg-muted">{{ t('venues.noCoordinates') }}</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <WorkspaceCard v-for="s in items" :key="s.id" :space="s" />
                    </div>
                </template>
                <EmptyState
                    v-else-if="!items.length"
                    :title="t('spaces.empty')"
                    :description="t('spaces.emptyHint')"
                >
                    <template #action>
                        <Button variant="secondary" @click="reset">{{ t('spaces.reset') }}</Button>
                    </template>
                </EmptyState>

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <WorkspaceCard v-for="s in items" :key="s.id" :space="s" />
                </div>

                <Pagination v-if="viewMode === 'list' && catalog?.links" :links="catalog.links" />
            </div>
        </div>

        <!-- Mobile filter drawer -->
        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-40 bg-black/40 md:hidden"
            @click.self="drawerOpen = false"
        >
            <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-wz-bg p-4">
                <h2 class="mb-3 text-sm font-semibold">{{ t('spaces.filters') }}</h2>
                <form class="grid gap-3" @submit.prevent="apply">
                    <Input v-model="form.search" :placeholder="t('spaces.search')" />
                    <Input v-model="form.city" :placeholder="t('spaces.city')" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="form.min_price" type="number" :placeholder="t('spaces.minPrice')" />
                        <Input v-model="form.max_price" type="number" :placeholder="t('spaces.maxPrice')" />
                    </div>
                    <Input v-model="form.capacity" type="number" :placeholder="t('spaces.capacity', { n: '' }).trim()" />
                    <select
                        v-model="form.type"
                        class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm"
                    >
                        <option value="">{{ t('venues.filterType') }}</option>
                        <option v-for="type in unitTypes" :key="type" :value="type">
                            {{ typeLabel(type) }}
                        </option>
                    </select>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.featured" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('spaces.onlyFeatured') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.open_now" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('availability.openNowFilter') }}
                    </label>

                    <div class="grid gap-2 rounded-xl border border-wz-border p-3">
                        <p class="text-sm font-semibold">{{ t('nearMe.title') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <input
                                data-testid="near-lat-mobile"
                                dir="ltr"
                                inputmode="decimal"
                                class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm"
                                :placeholder="t('venues.latitude')"
                                :value="form.near_lat"
                                @input="form.near_lat = $event.target.value"
                                @paste="applyPaste('lat', $event)"
                            />
                            <input
                                data-testid="near-lng-mobile"
                                dir="ltr"
                                inputmode="decimal"
                                class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm"
                                :placeholder="t('venues.longitude')"
                                :value="form.near_lng"
                                @input="form.near_lng = $event.target.value"
                                @paste="applyPaste('lng', $event)"
                            />
                        </div>
                        <select v-model="form.radius_km" class="wz-focus rounded-xl border border-wz-border bg-wz-elevated px-2.5 py-2 text-sm">
                            <option value="any">{{ t('nearMe.radiusAny') }}</option>
                            <option value="5">5 km</option>
                            <option value="10">10 km</option>
                            <option value="25">25 km</option>
                            <option value="50">50 km</option>
                            <option value="100">100 km</option>
                        </select>
                        <Button type="button" size="sm" variant="secondary" :disabled="geoBusy" @click="useMyLocation">
                            {{ t('nearMe.useMyLocation') }}
                        </Button>
                        <p v-if="geoMessage" class="text-xs text-wz-danger">{{ geoMessage }}</p>
                    </div>

                    <div class="flex gap-2">
                        <Button type="submit" class="flex-1">{{ t('spaces.apply') }}</Button>
                        <Button type="button" variant="secondary" @click="reset">{{ t('spaces.reset') }}</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
