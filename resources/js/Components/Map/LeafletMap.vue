<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { buildMarkerPopupHtml, fitMapToMarkers } from '@/utils/mapPopup';

const props = defineProps({
    lat: { type: Number, default: null },
    lng: { type: Number, default: null },
    tileUrl: { type: String, required: true },
    attribution: { type: String, default: '' },
    heightClass: { type: String, default: 'h-56' },
    interactive: { type: Boolean, default: false },
    /** When true, map clicks set / emit userLocation (near-me pick mode). */
    pickUserLocation: { type: Boolean, default: false },
    /** Visitor "you are here" point for near-me. */
    userLocation: { type: Object, default: null },
    markers: { type: Array, default: () => [] },
    center: { type: Object, default: () => ({ lat: 31.5017, lng: 34.4668, zoom: 11 }) },
});

const emit = defineEmits(['update:lat', 'update:lng', 'update:userLocation', 'select']);

const { t, locale } = useI18n();
const el = ref(null);
let map = null;
let marker = null;
let userMarker = null;
let markerLayer = null;

// Bundle marker icons from local public path (no CDN).
const icon = L.icon({
    iconUrl: '/vendor/leaflet/images/marker-icon.png',
    iconRetinaUrl: '/vendor/leaflet/images/marker-icon-2x.png',
    shadowUrl: '/vendor/leaflet/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

const youAreHereIcon = L.divIcon({
    className: 'wz-you-are-here',
    html: '<span class="wz-you-are-here__dot" title="You are here"></span>',
    iconSize: [18, 18],
    iconAnchor: [9, 9],
});

function popupLabels() {
    return {
        fromPrice: ({ price }) => t('venues.mapFromPrice', { price }),
        details: t('spaces.details'),
        distance: ({ distance }) => t('nearMe.distanceKm', { distance }),
    };
}

function init() {
    if (!el.value || map) return;

    // Prefer marker fit over a distant default; provisional center until markers render.
    const hasPin = props.lat != null && props.lng != null;
    const hasUser = props.userLocation?.lat != null && props.userLocation?.lng != null;
    const hasMarkers = (props.markers || []).some((m) => m.lat != null && m.lng != null);
    const lat = hasPin
        ? props.lat
        : (hasUser ? props.userLocation.lat : (props.center.lat ?? 31.5017));
    const lng = hasPin
        ? props.lng
        : (hasUser ? props.userLocation.lng : (props.center.lng ?? 34.4668));
    const zoom = hasPin || hasMarkers || hasUser ? (props.center.zoom || 11) : (props.center.zoom || 11);

    map = L.map(el.value, {
        scrollWheelZoom: props.interactive || props.pickUserLocation,
        dragging: true,
        attributionControl: true,
    }).setView([lat, lng], zoom);

    L.tileLayer(props.tileUrl, {
        attribution: props.attribution,
        maxZoom: 19,
    }).addTo(map);

    markerLayer = L.layerGroup().addTo(map);

    map.on('click', (e) => {
        if (props.pickUserLocation) {
            const la = Number(e.latlng.lat.toFixed(3));
            const ln = Number(e.latlng.lng.toFixed(3));
            setUserPin(la, ln);
            emit('update:userLocation', { lat: la, lng: ln });
            return;
        }
        if (props.interactive) {
            setPin(e.latlng.lat, e.latlng.lng);
            emit('update:lat', Number(e.latlng.lat.toFixed(7)));
            emit('update:lng', Number(e.latlng.lng.toFixed(7)));
        }
    });

    if (props.lat != null && props.lng != null) {
        setPin(props.lat, props.lng);
    }

    renderUserPin();
    renderMarkers();
    setTimeout(() => {
        map?.invalidateSize();
        // Re-fit after layout so we do not flash a world view.
        fitToCurrentMarkers();
    }, 50);
}

function setPin(lat, lng) {
    if (!map) return;
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { icon, draggable: props.interactive }).addTo(map);
        if (props.interactive) {
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                emit('update:lat', Number(p.lat.toFixed(7)));
                emit('update:lng', Number(p.lng.toFixed(7)));
            });
        }
    }
    map.setView([lat, lng], Math.max(map.getZoom(), 14));
}

function clearPin() {
    if (!map || !marker) return;
    map.removeLayer(marker);
    marker = null;
}

function setUserPin(lat, lng) {
    if (!map) return;
    if (userMarker) {
        userMarker.setLatLng([lat, lng]);
    } else {
        userMarker = L.marker([lat, lng], {
            icon: youAreHereIcon,
            zIndexOffset: 1000,
            keyboard: false,
        }).addTo(map);
        userMarker.bindTooltip(t('nearMe.youAreHere'), {
            permanent: false,
            direction: 'top',
            offset: [0, -8],
        });
    }
}

function clearUserPin() {
    if (!map || !userMarker) return;
    map.removeLayer(userMarker);
    userMarker = null;
}

function renderUserPin() {
    const u = props.userLocation;
    if (u?.lat != null && u?.lng != null && Number.isFinite(Number(u.lat)) && Number.isFinite(Number(u.lng))) {
        setUserPin(Number(u.lat), Number(u.lng));
    } else {
        clearUserPin();
    }
}

function markerBounds() {
    const bounds = [];
    (props.markers || []).forEach((m) => {
        if (m.lat == null || m.lng == null) return;
        bounds.push([Number(m.lat), Number(m.lng)]);
    });
    const u = props.userLocation;
    if (u?.lat != null && u?.lng != null) {
        bounds.push([Number(u.lat), Number(u.lng)]);
    }
    return bounds;
}

function fitToCurrentMarkers() {
    if (!map) return;
    fitMapToMarkers(map, markerBounds(), { padding: [40, 40], maxZoom: 15, singleZoom: 14 });
}

function renderMarkers() {
    if (!map || !markerLayer) return;
    markerLayer.clearLayers();
    const labels = popupLabels();
    props.markers.forEach((m) => {
        if (m.lat == null || m.lng == null) return;
        const mk = L.marker([m.lat, m.lng], { icon }).addTo(markerLayer);
        mk.bindPopup(buildMarkerPopupHtml(m, labels, locale.value));
        mk.on('click', () => emit('select', m));
    });
    fitToCurrentMarkers();
}

onMounted(init);
onBeforeUnmount(() => {
    map?.remove();
    map = null;
});

watch(() => [props.lat, props.lng], ([lat, lng]) => {
    if (lat != null && lng != null && Number.isFinite(lat) && Number.isFinite(lng)) {
        setPin(lat, lng);
    } else {
        clearPin();
    }
});

watch(() => props.userLocation, () => {
    renderUserPin();
    fitToCurrentMarkers();
}, { deep: true });

watch(() => props.markers, () => renderMarkers(), { deep: true });
watch(locale, () => {
    renderMarkers();
    if (userMarker) {
        userMarker.unbindTooltip();
        userMarker.bindTooltip(t('nearMe.youAreHere'), {
            permanent: false,
            direction: 'top',
            offset: [0, -8],
        });
    }
});
</script>

<template>
    <div
        ref="el"
        :class="[heightClass, 'w-full overflow-hidden rounded-xl border border-wz-border bg-wz-muted']"
        role="application"
        :aria-label="t('availability.mapView')"
        data-testid="leaflet-map"
    />
</template>

<style>
.wz-you-are-here {
    background: transparent;
    border: none;
}
.wz-you-are-here__dot {
    display: block;
    width: 16px;
    height: 16px;
    border-radius: 9999px;
    background: #2563eb;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #2563eb, 0 1px 4px rgb(0 0 0 / 0.35);
}
</style>
