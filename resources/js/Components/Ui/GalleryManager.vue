<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Badge from './Badge.vue';
import Button from './Button.vue';

const props = defineProps({
    workspaceId: { type: Number, default: null },
    images: { type: Array, default: () => [] },
});

const emit = defineEmits(['files']);

const { t } = useI18n();
const localImages = ref([]);
const busy = ref(false);

watch(
    () => props.images,
    (value) => {
        localImages.value = [...(value || [])].sort(
            (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0),
        );
    },
    { immediate: true, deep: true },
);

const canManage = computed(() => Boolean(props.workspaceId));

function onFiles(e) {
    emit('files', Array.from(e.target.files || []));
}

function setPrimary(image) {
    if (!canManage.value || busy.value) return;
    busy.value = true;
    router.post(
        route('owner.workspaces.images.primary', [props.workspaceId, image.id]),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function removeImage(image) {
    if (!canManage.value || busy.value) return;
    busy.value = true;
    router.delete(route('owner.workspaces.images.destroy', [props.workspaceId, image.id]), {
        preserveScroll: true,
        onFinish: () => {
            busy.value = false;
        },
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
    router.put(
        route('owner.workspaces.images.reorder', props.workspaceId),
        { order: copy.map((img) => img.id) },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-medium text-wz-fg">{{ t('owner.gallery') }}</h3>
            <label class="inline-flex cursor-pointer">
                <span class="sr-only">{{ t('owner.uploadImages') }}</span>
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    multiple
                    class="wz-focus block w-full text-sm text-wz-fg file:me-3 file:rounded-lg file:border-0 file:bg-wz-brand-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-wz-brand disabled:cursor-not-allowed disabled:opacity-55"
                    :disabled="busy"
                    @change="onFiles"
                />
            </label>
        </div>

        <p v-if="!localImages.length" class="text-sm text-wz-fg-muted">
            {{ t('owner.uploadImages') }}
        </p>

        <ul v-else class="grid gap-3 sm:grid-cols-2">
            <li
                v-for="(img, index) in localImages"
                :key="img.id"
                class="overflow-hidden rounded-xl border border-wz-border bg-wz-elevated"
            >
                <img :src="img.url" alt="" class="h-36 w-full object-cover" />
                <div class="flex flex-wrap items-center gap-2 p-3">
                    <Badge v-if="img.is_primary" tone="brand">{{ t('owner.primary') }}</Badge>
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
            </li>
        </ul>
    </div>
</template>
