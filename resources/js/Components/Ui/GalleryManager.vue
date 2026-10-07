<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Badge from './Badge.vue';
import Button from './Button.vue';

const props = defineProps({
    workspaceId: { type: Number, default: null },
    venueId: { type: [Number, String], default: null },
    venueSlug: { type: String, default: null },
    images: { type: Array, default: () => [] },
    routePrefix: { type: String, default: 'owner' }, // owner | admin
});

const emit = defineEmits(['files']);

const { t } = useI18n();
const localImages = ref([]);
const busy = ref(false);
const dragOver = ref(false);
const pending = ref([]); // { id, name, progress, error }

watch(
    () => props.images,
    (value) => {
        localImages.value = [...(value || [])].sort(
            (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0),
        );
    },
    { immediate: true, deep: true },
);

const canManageWorkspace = computed(() => Boolean(props.workspaceId));
const canManageVenue = computed(() => Boolean(props.venueSlug));
const canManage = computed(() => canManageWorkspace.value || canManageVenue.value);

function primaryRoute(image) {
    if (canManageVenue.value) {
        return route(`${props.routePrefix}.venues.images.primary`, [props.venueSlug, image.id]);
    }
    return route('owner.workspaces.images.primary', [props.workspaceId, image.id]);
}

function destroyRoute(image) {
    if (canManageVenue.value) {
        return route(`${props.routePrefix}.venues.images.destroy`, [props.venueSlug, image.id]);
    }
    return route('owner.workspaces.images.destroy', [props.workspaceId, image.id]);
}

function reorderRoute() {
    if (canManageVenue.value) {
        return route(`${props.routePrefix}.venues.images.reorder`, props.venueSlug);
    }
    return route('owner.workspaces.images.reorder', props.workspaceId);
}

function storeRoute() {
    return route(`${props.routePrefix}.venues.images.store`, props.venueSlug);
}

function onFiles(e) {
    const files = Array.from(e.target.files || []);
    handleFiles(files);
    e.target.value = '';
}

function onDrop(e) {
    dragOver.value = false;
    const files = Array.from(e.dataTransfer?.files || []).filter((f) => f.type.startsWith('image/'));
    handleFiles(files);
}

function handleFiles(files) {
    if (!files.length) return;

    if (canManageVenue.value) {
        uploadVenueFiles(files);
        return;
    }

    emit('files', files);
}

function uploadVenueFiles(files) {
    if (busy.value) return;
    busy.value = true;
    pending.value = files.map((f, i) => ({
        id: `${Date.now()}-${i}`,
        name: f.name,
        progress: 10,
        error: null,
    }));

    const form = new FormData();
    files.forEach((f) => form.append('images[]', f));

    router.post(storeRoute(), form, {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (event) => {
            const pct = event?.percentage ?? 50;
            pending.value = pending.value.map((p) => ({ ...p, progress: pct }));
        },
        onError: () => {
            pending.value = pending.value.map((p) => ({
                ...p,
                error: t('owner.uploadFailed', 'Upload failed'),
                progress: 100,
            }));
        },
        onFinish: () => {
            busy.value = false;
            setTimeout(() => {
                pending.value = [];
            }, 600);
        },
    });
}

function setPrimary(image) {
    if (!canManage.value || busy.value) return;
    busy.value = true;
    router.post(primaryRoute(image), {}, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function removeImage(image) {
    if (!canManage.value || busy.value) return;
    busy.value = true;
    router.delete(destroyRoute(image), {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function move(index, delta) {
    if (!canManage.value || busy.value) return;
    const next = index + delta;
    if (next < 0 || next >= localImages.value.length) return;
    const copy = [...localImages.value];
    const [item] = copy.splice(index, 1);
    copy.splice(next, 0, item);
    localImages.value = copy;
    busy.value = true;
    router.put(reorderRoute(), { order: copy.map((img) => img.id) }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; },
    });
}

function saveCaption(image, event) {
    if (!canManageVenue.value || busy.value) return;
    const caption = event.target.value;
    busy.value = true;
    router.put(
        route(`${props.routePrefix}.venues.images.caption`, [props.venueSlug, image.id]),
        { caption },
        { preserveScroll: true, onFinish: () => { busy.value = false; } },
    );
}

function thumbSrc(img) {
    return img.card_url || img.url || '';
}
</script>

<template>
    <div class="space-y-3" data-testid="gallery-manager">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-medium text-wz-fg">{{ t('owner.gallery') }}</h3>
        </div>

        <div
            class="rounded-xl border border-dashed px-4 py-6 text-center transition"
            :class="dragOver ? 'border-wz-brand bg-wz-brand-soft/40' : 'border-wz-border bg-wz-muted/30'"
            data-testid="gallery-dropzone"
            @dragenter.prevent="dragOver = true"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="onDrop"
        >
            <p class="text-sm text-wz-fg-muted">{{ t('owner.dropImagesHint') }}</p>
            <label class="mt-3 inline-flex cursor-pointer">
                <span class="sr-only">{{ t('owner.uploadImages') }}</span>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    multiple
                    class="wz-focus block w-full max-w-xs text-sm text-wz-fg file:me-3 file:rounded-lg file:border-0 file:bg-wz-brand-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-wz-brand disabled:cursor-not-allowed disabled:opacity-55"
                    :disabled="busy"
                    data-testid="gallery-file-input"
                    @change="onFiles"
                />
            </label>
        </div>

        <ul v-if="pending.length" class="space-y-2">
            <li
                v-for="p in pending"
                :key="p.id"
                class="rounded-lg border border-wz-border bg-wz-elevated px-3 py-2 text-xs"
            >
                <div class="flex justify-between gap-2">
                    <span class="truncate">{{ p.name }}</span>
                    <span>{{ p.progress }}%</span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded bg-wz-muted">
                    <div class="h-full bg-wz-brand transition-all" :style="{ width: `${p.progress}%` }" />
                </div>
                <p v-if="p.error" class="mt-1 text-wz-danger">{{ p.error }}</p>
            </li>
        </ul>

        <p v-if="!localImages.length && !pending.length" class="text-sm text-wz-fg-muted">
            {{ t('owner.uploadImages') }}
        </p>

        <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li
                v-for="(img, index) in localImages"
                :key="img.id"
                class="overflow-hidden rounded-xl border border-wz-border bg-wz-elevated"
            >
                <div class="aspect-[4/3] bg-wz-muted">
                    <img
                        :src="thumbSrc(img)"
                        alt=""
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                </div>
                <div class="flex flex-wrap items-center gap-2 p-3">
                    <Badge v-if="img.is_primary" tone="brand">{{ t('owner.primary') }}</Badge>
                    <Badge v-if="img.is_demo" tone="warning">{{ t('owner.demoImage') }}</Badge>
                    <template v-if="canManage">
                        <Button
                            v-if="!img.is_primary"
                            size="sm"
                            variant="secondary"
                            :disabled="busy"
                            @click="setPrimary(img)"
                        >
                            {{ t('owner.setPrimary') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy || index === 0"
                            @click="move(index, -1)"
                        >
                            {{ t('owner.moveUp') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="busy || index === localImages.length - 1"
                            @click="move(index, 1)"
                        >
                            {{ t('owner.moveDown') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="danger"
                            :disabled="busy"
                            @click="removeImage(img)"
                        >
                            {{ t('owner.removeImage') }}
                        </Button>
                    </template>
                </div>
                <div v-if="canManageVenue" class="border-t border-wz-border px-3 py-2">
                    <input
                        type="text"
                        class="wz-focus w-full rounded-lg border border-wz-border bg-wz-bg px-2 py-1.5 text-xs"
                        :placeholder="t('owner.captionOptional')"
                        :value="img.caption || ''"
                        :disabled="busy"
                        @change="saveCaption(img, $event)"
                    />
                </div>
            </li>
        </ul>
    </div>
</template>
