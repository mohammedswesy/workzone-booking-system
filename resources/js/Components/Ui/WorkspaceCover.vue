<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { workspaceCoverUrl } from '@/utils/workspaceImage';

const props = defineProps({
    space: { type: Object, default: () => ({}) },
    alt: { type: String, default: '' },
    imgClass: { type: String, default: 'h-full w-full object-cover' },
});

const { t } = useI18n();

const src = computed(() => workspaceCoverUrl(props.space));
</script>

<template>
    <div class="aspect-[4/3] w-full overflow-hidden bg-wz-muted" data-testid="workspace-cover">
        <img
            v-if="src"
            :src="src"
            :alt="alt || space.name || ''"
            loading="lazy"
            decoding="async"
            :class="imgClass"
            data-testid="workspace-cover-img"
        />
        <div
            v-else
            class="flex h-full w-full items-center justify-center text-wz-fg-muted"
            role="img"
            :aria-label="t('spaces.noImage')"
            data-testid="workspace-cover-fallback"
        >
            <span class="text-xs font-medium tracking-wide uppercase">{{ t('spaces.noImage') }}</span>
        </div>
    </div>
</template>
