<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();

/**
 * Laravel sends: [prev, ...pages, next]. We ignore raw labels and rebuild UI.
 */
const model = computed(() => {
    const links = Array.isArray(props.links) ? props.links : [];
    if (links.length < 3) {
        return null;
    }

    const prev = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1).map((link) => ({
        url: link.url,
        active: Boolean(link.active),
        // Strip HTML entities; keep numeric / ellipsis labels only for page slots.
        label: String(link.label ?? '').replace(/<[^>]+>/g, '').trim(),
    }));

    return {
        prevUrl: prev?.url ?? null,
        nextUrl: next?.url ?? null,
        pages,
    };
});

const itemBase =
    'inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-xl border px-3 py-1.5 text-sm font-medium transition wz-focus';
</script>

<template>
    <nav
        v-if="model"
        class="mt-6 flex flex-wrap items-center gap-1.5"
        :aria-label="t('pagination.label')"
    >
        <!-- Previous -->
        <Link
            v-if="model.prevUrl"
            :href="model.prevUrl"
            :class="[
                itemBase,
                'border-wz-border bg-wz-elevated text-wz-fg hover:border-wz-brand hover:bg-wz-brand-soft hover:text-wz-brand',
            ]"
        >
            <span class="inline-block rtl:rotate-180" aria-hidden="true">←</span>
            <span>{{ t('pagination.previous') }}</span>
        </Link>
        <span
            v-else
            :class="[
                itemBase,
                'cursor-not-allowed border-wz-border bg-wz-muted text-wz-fg opacity-55',
            ]"
            aria-disabled="true"
        >
            <span class="inline-block rtl:rotate-180" aria-hidden="true">←</span>
            <span>{{ t('pagination.previous') }}</span>
        </span>

        <!-- Page numbers -->
        <template v-for="(page, index) in model.pages" :key="`${page.label}-${index}`">
            <Link
                v-if="page.url && page.label !== '...'"
                :href="page.url"
                :aria-current="page.active ? 'page' : undefined"
                :class="[
                    itemBase,
                    page.active
                        ? 'border-wz-brand bg-wz-brand text-wz-brand-fg shadow-sm hover:opacity-90'
                        : 'border-wz-border bg-wz-elevated text-wz-fg hover:border-wz-brand hover:bg-wz-brand-soft hover:text-wz-brand',
                ]"
            >
                {{ page.label }}
            </Link>
            <span
                v-else-if="page.label === '...'"
                :class="[itemBase, 'cursor-default border-transparent text-wz-fg-muted']"
            >
                …
            </span>
            <span
                v-else
                :class="[
                    itemBase,
                    page.active
                        ? 'border-wz-brand bg-wz-brand text-wz-brand-fg'
                        : 'cursor-not-allowed border-wz-border bg-wz-muted text-wz-fg opacity-55',
                ]"
                :aria-current="page.active ? 'page' : undefined"
                aria-disabled="true"
            >
                {{ page.label }}
            </span>
        </template>

        <!-- Next -->
        <Link
            v-if="model.nextUrl"
            :href="model.nextUrl"
            :class="[
                itemBase,
                'border-wz-border bg-wz-elevated text-wz-fg hover:border-wz-brand hover:bg-wz-brand-soft hover:text-wz-brand',
            ]"
        >
            <span>{{ t('pagination.next') }}</span>
            <span class="inline-block rtl:rotate-180" aria-hidden="true">→</span>
        </Link>
        <span
            v-else
            :class="[
                itemBase,
                'cursor-not-allowed border-wz-border bg-wz-muted text-wz-fg opacity-55',
            ]"
            aria-disabled="true"
        >
            <span>{{ t('pagination.next') }}</span>
            <span class="inline-block rtl:rotate-180" aria-hidden="true">→</span>
        </span>
    </nav>
</template>
