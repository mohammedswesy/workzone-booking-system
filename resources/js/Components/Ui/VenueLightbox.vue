<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    images: { type: Array, default: () => [] },
    startIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['close']);
const { t } = useI18n();
const index = ref(props.startIndex);
const touchX = ref(null);

const current = computed(() => props.images[index.value] || null);
const src = computed(() => current.value?.gallery_url || current.value?.url || '');

watch(() => props.startIndex, (v) => { index.value = v; });

function prev() {
    if (!props.images.length) return;
    index.value = (index.value - 1 + props.images.length) % props.images.length;
}

function next() {
    if (!props.images.length) return;
    index.value = (index.value + 1) % props.images.length;
}

function onKey(e) {
    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowLeft') prev();
    if (e.key === 'ArrowRight') next();
}

function onTouchStart(e) {
    touchX.value = e.changedTouches?.[0]?.clientX ?? null;
}

function onTouchEnd(e) {
    if (touchX.value == null) return;
    const x = e.changedTouches?.[0]?.clientX ?? touchX.value;
    const dx = x - touchX.value;
    touchX.value = null;
    if (Math.abs(dx) < 40) return;
    if (dx < 0) next();
    else prev();
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
        role="dialog"
        aria-modal="true"
        data-testid="venue-lightbox"
        @click.self="emit('close')"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <button
            type="button"
            class="absolute end-4 top-4 rounded-lg bg-white/10 px-3 py-1.5 text-sm text-white"
            @click="emit('close')"
        >
            {{ t('common.close', 'Close') }}
        </button>
        <button
            type="button"
            class="absolute start-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 px-3 py-2 text-white"
            @click="prev"
        >
            ‹
        </button>
        <button
            type="button"
            class="absolute end-3 top-1/2 -translate-y-1/2 rounded-full bg-white/10 px-3 py-2 text-white"
            @click="next"
        >
            ›
        </button>
        <div class="max-h-full max-w-5xl">
            <img
                v-if="src"
                :src="src"
                :alt="current?.caption || t('spaces.gallery')"
                class="max-h-[80vh] w-full object-contain"
            />
            <p v-if="current?.caption" class="mt-3 text-center text-sm text-white/90">
                {{ current.caption }}
            </p>
            <p class="mt-2 text-center text-xs text-white/60">
                {{ index + 1 }} / {{ images.length }}
            </p>
        </div>
    </div>
</template>
