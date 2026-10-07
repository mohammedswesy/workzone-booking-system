<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Button from '@/Components/Ui/Button.vue';
import LeafletMap from '@/Components/Map/LeafletMap.vue';
import {
    coordsComplete,
    looksSwapped,
    parseCoordinatePaste,
    roundCoord,
} from '@/utils/coordinates';
import { distanceKm, mapGeolocationError, requestUserLocation } from '@/utils/geolocation';

const FAR_FROM_CITY_KM = 100;

const props = defineProps({
    form: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
    cityCenters: { type: Object, default: () => ({}) },
    showOwnerSelect: { type: Boolean, default: false },
    idPrefix: { type: String, default: 'venue' },
});

const { t } = useI18n();
const page = usePage();
const map = computed(() => page.props.map || {
    tile_url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    tile_attribution: '&copy; OpenStreetMap',
    default_center: { lat: 31.5017, lng: 34.4668, zoom: 11 },
});

const latText = ref(props.form.lat != null && props.form.lat !== '' ? String(props.form.lat) : '');
const lngText = ref(props.form.lng != null && props.form.lng !== '' ? String(props.form.lng) : '');
const pasteHint = ref('');
const swapHint = ref(false);
const geoMessage = ref('');
const geoBusy = ref(false);

const mapLat = computed(() => {
    const n = roundCoord(props.form.lat);
    return n;
});
const mapLng = computed(() => {
    const n = roundCoord(props.form.lng);
    return n;
});

const googleMapsUrl = computed(() => {
    if (!coordsComplete(props.form.lat, props.form.lng)) return null;
    return `https://www.google.com/maps?q=${props.form.lat},${props.form.lng}`;
});

const isZeroZero = computed(() => {
    if (!coordsComplete(props.form.lat, props.form.lng)) return false;
    return Math.abs(Number(props.form.lat)) < 0.0001 && Math.abs(Number(props.form.lng)) < 0.0001;
});

const farFromCity = computed(() => {
    if (!coordsComplete(props.form.lat, props.form.lng) || isZeroZero.value) return false;
    const loc = props.locations.find((l) => Number(l.id) === Number(props.form.location_id));
    const city = loc?.city;
    const center = city ? props.cityCenters?.[city] : null;
    if (!center?.lat || !center?.lng) return false;
    return distanceKm(
        Number(props.form.lat),
        Number(props.form.lng),
        Number(center.lat),
        Number(center.lng),
    ) > FAR_FROM_CITY_KM;
});

watch(
    () => [props.form.lat, props.form.lng],
    ([lat, lng]) => {
        const latStr = lat != null && lat !== '' ? String(lat) : '';
        const lngStr = lng != null && lng !== '' ? String(lng) : '';
        if (latStr !== latText.value) latText.value = latStr;
        if (lngStr !== lngText.value) lngText.value = lngStr;
        swapHint.value = looksSwapped(lat, lng);
    },
);

function syncFormFromTexts() {
    const lat = latText.value.trim() === '' ? null : roundCoord(latText.value);
    const lng = lngText.value.trim() === '' ? null : roundCoord(lngText.value);
    props.form.lat = lat;
    props.form.lng = lng;
    swapHint.value = looksSwapped(lat, lng);
    pasteHint.value = '';
}

function onLatInput(e) {
    latText.value = e.target.value;
    syncFormFromTexts();
}

function onLngInput(e) {
    lngText.value = e.target.value;
    syncFormFromTexts();
}

function onPinLat(v) {
    const n = roundCoord(v);
    props.form.lat = n;
    latText.value = n == null ? '' : String(n);
    pasteHint.value = '';
    swapHint.value = looksSwapped(props.form.lat, props.form.lng);
}

function onPinLng(v) {
    const n = roundCoord(v);
    props.form.lng = n;
    lngText.value = n == null ? '' : String(n);
    pasteHint.value = '';
    swapHint.value = looksSwapped(props.form.lat, props.form.lng);
}

function applyPaste(field, event) {
    const text = event.clipboardData?.getData('text') ?? '';
    const parsed = parseCoordinatePaste(text);

    if (parsed.kind === 'pair') {
        event.preventDefault();
        latText.value = String(parsed.lat);
        lngText.value = String(parsed.lng);
        props.form.lat = parsed.lat;
        props.form.lng = parsed.lng;
        pasteHint.value = '';
        swapHint.value = looksSwapped(parsed.lat, parsed.lng);
        return;
    }

    if (parsed.kind === 'url_unparsed') {
        event.preventDefault();
        pasteHint.value = t('venues.pasteCoordsHint');
        return;
    }

    if (parsed.kind === 'single') {
        event.preventDefault();
        if (field === 'lat') {
            latText.value = String(parsed.value);
            props.form.lat = parsed.value;
        } else {
            lngText.value = String(parsed.value);
            props.form.lng = parsed.value;
        }
        pasteHint.value = '';
        swapHint.value = looksSwapped(props.form.lat, props.form.lng);
        return;
    }

    // Let the browser paste raw text for invalid / empty — then normalize on input.
    pasteHint.value = '';
}

function clearLocation() {
    latText.value = '';
    lngText.value = '';
    props.form.lat = null;
    props.form.lng = null;
    pasteHint.value = '';
    swapHint.value = false;
    geoMessage.value = '';
}

async function useMyLocation() {
    geoBusy.value = true;
    geoMessage.value = '';
    try {
        const pos = await requestUserLocation({ decimals: 7, enableHighAccuracy: true });
        onPinLat(pos.lat);
        onPinLng(pos.lng);
    } catch (err) {
        geoMessage.value = t(`nearMe.geo.${mapGeolocationError(err)}`);
    } finally {
        geoBusy.value = false;
    }
}

function toggleAmenity(id) {
    const key = Number(id);
    if (props.form.amenities.includes(key)) {
        props.form.amenities = props.form.amenities.filter((a) => a !== key);
    } else {
        props.form.amenities.push(key);
    }
}
</script>

<template>
    <div class="space-y-5">
        <div v-if="showOwnerSelect" class="space-y-2">
            <Select
                :id="`${idPrefix}-owner`"
                :model-value="form.owner_id ?? ''"
                :error="form.errors?.owner_id"
                :disabled="form.processing"
                @update:model-value="form.owner_id = $event ? Number($event) : null"
            >
                <template #label>{{ t('admin.owner') }}</template>
                <option value="" disabled>{{ t('admin.selectOwner') }}</option>
                <option v-for="o in owners" :key="o.id" :value="o.id">
                    {{ o.name }} — {{ o.email }}
                </option>
            </Select>
        </div>

        <Input
            :id="`${idPrefix}-name`"
            v-model="form.name"
            :error="form.errors?.name"
            :disabled="form.processing"
        >
            <template #label>{{ t('venues.name') }}</template>
        </Input>

        <Input
            :id="`${idPrefix}-slug`"
            v-model="form.slug"
            :error="form.errors?.slug"
            :disabled="form.processing"
        >
            <template #label>{{ t('venues.slug') }}</template>
        </Input>

        <label class="grid gap-1.5">
            <span class="text-sm font-medium text-wz-fg">{{ t('owner.description') }}</span>
            <textarea
                v-model="form.description"
                rows="4"
                class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm"
                :disabled="form.processing"
            />
        </label>

        <Select
            :id="`${idPrefix}-location`"
            :model-value="form.location_id ?? ''"
            :error="form.errors?.location_id"
            :disabled="form.processing"
            @update:model-value="form.location_id = $event ? Number($event) : null"
        >
            <template #label>{{ t('venues.location') }}</template>
            <option value="">{{ t('common.optional') }}</option>
            <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                {{ loc.name }}{{ loc.city ? ` — ${loc.city}` : '' }}
            </option>
        </Select>

        <Input
            :id="`${idPrefix}-address`"
            v-model="form.address"
            :error="form.errors?.address"
            :disabled="form.processing"
        >
            <template #label>{{ t('venues.address') }}</template>
        </Input>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium text-wz-fg">{{ t('venues.pickOnMap') }}</legend>
            <p class="text-xs text-wz-fg-muted">{{ t('venues.mapPinHint') }}</p>

            <LeafletMap
                :lat="mapLat"
                :lng="mapLng"
                :tile-url="map.tile_url"
                :attribution="map.tile_attribution"
                :center="map.default_center"
                interactive
                height-class="h-56 sm:h-64"
                @update:lat="onPinLat"
                @update:lng="onPinLng"
            />

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <label :for="`${idPrefix}-lat`" class="text-sm font-medium text-wz-fg">
                        {{ t('venues.latitude') }}
                    </label>
                    <input
                        :id="`${idPrefix}-lat`"
                        data-testid="venue-lat"
                        dir="ltr"
                        inputmode="decimal"
                        autocomplete="off"
                        :value="latText"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors?.lat)"
                        :aria-describedby="`${idPrefix}-lat-hint`"
                        class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:cursor-not-allowed disabled:opacity-55"
                        :class="form.errors?.lat ? 'border-wz-danger' : ''"
                        @input="onLatInput"
                        @paste="applyPaste('lat', $event)"
                    />
                    <p v-if="form.errors?.lat" class="text-xs text-wz-danger">{{ form.errors.lat }}</p>
                </div>
                <div class="grid gap-1.5">
                    <label :for="`${idPrefix}-lng`" class="text-sm font-medium text-wz-fg">
                        {{ t('venues.longitude') }}
                    </label>
                    <input
                        :id="`${idPrefix}-lng`"
                        data-testid="venue-lng"
                        dir="ltr"
                        inputmode="decimal"
                        autocomplete="off"
                        :value="lngText"
                        :disabled="form.processing"
                        :aria-invalid="Boolean(form.errors?.lng)"
                        :aria-describedby="`${idPrefix}-lng-hint`"
                        class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:cursor-not-allowed disabled:opacity-55"
                        :class="form.errors?.lng ? 'border-wz-danger' : ''"
                        @input="onLngInput"
                        @paste="applyPaste('lng', $event)"
                    />
                    <p v-if="form.errors?.lng" class="text-xs text-wz-danger">{{ form.errors.lng }}</p>
                </div>
            </div>

            <p
                v-if="pasteHint"
                :id="`${idPrefix}-lat-hint`"
                class="text-xs text-wz-warning"
                role="status"
            >
                {{ pasteHint }}
            </p>
            <p
                v-else-if="swapHint"
                :id="`${idPrefix}-lng-hint`"
                class="text-xs text-wz-warning"
                role="status"
            >
                {{ t('venues.coordsSwappedHint') }}
            </p>
            <p v-else class="text-xs text-wz-fg-muted">
                {{ t('venues.coordsPasteHelp') }}
            </p>

            <p
                v-if="isZeroZero"
                class="text-xs text-wz-warning"
                role="status"
                data-testid="zero-zero-warning"
            >
                {{ t('venues.zeroZeroWarning') }}
            </p>
            <p
                v-else-if="farFromCity"
                class="text-xs text-wz-warning"
                role="status"
                data-testid="far-from-city-warning"
            >
                {{ t('venues.farFromCityWarning') }}
            </p>
            <p
                v-if="geoMessage"
                class="text-xs text-wz-danger"
                role="alert"
                data-testid="venue-geo-message"
            >
                {{ geoMessage }}
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    data-testid="use-my-location"
                    :disabled="form.processing || geoBusy"
                    @click="useMyLocation"
                >
                    {{ t('venues.useMyLocation') }}
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    data-testid="clear-location"
                    :disabled="form.processing || (form.lat == null && form.lng == null)"
                    @click="clearLocation"
                >
                    {{ t('venues.clearLocation') }}
                </Button>
                <a
                    v-if="googleMapsUrl"
                    :href="googleMapsUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-sm text-wz-brand underline-offset-2 hover:underline"
                    data-testid="open-in-maps"
                >
                    {{ t('venues.openInMaps') }}
                </a>
            </div>
        </fieldset>

        <Select
            :id="`${idPrefix}-status`"
            v-model="form.status"
            :error="form.errors?.status"
            :disabled="form.processing"
        >
            <template #label>{{ t('common.status') }}</template>
            <option value="draft">{{ t('venues.statusDraft') }}</option>
            <option value="published">{{ t('venues.statusPublished') }}</option>
            <option value="archived">{{ t('venues.statusArchived') }}</option>
        </Select>

        <label class="flex items-center gap-2 text-sm">
            <input v-model="form.featured" type="checkbox" class="rounded border-wz-border" :disabled="form.processing" />
            {{ t('spaces.featured') }}
        </label>

        <div>
            <p class="mb-2 text-sm font-medium">{{ t('owner.amenities') }}</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="a in amenities"
                    :key="a.id"
                    type="button"
                    class="rounded-lg border px-2 py-1 text-xs"
                    :class="form.amenities.includes(a.id) ? 'border-wz-brand bg-wz-brand-soft text-wz-brand' : 'border-wz-border'"
                    @click="toggleAmenity(a.id)"
                >
                    {{ a.name }}
                </button>
            </div>
        </div>
    </div>
</template>
