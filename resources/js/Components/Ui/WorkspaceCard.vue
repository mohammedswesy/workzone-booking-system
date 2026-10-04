<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Badge from './Badge.vue';
import Button from './Button.vue';

defineProps({
    space: { type: Object, required: true },
});

const { t } = useI18n();

function imageOf(space) {
    return (
        space.images?.[0]?.url ||
        space.image_url ||
        'https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=800&auto=format&fit=crop'
    );
}
</script>

<template>
    <article class="wz-surface flex h-full flex-col overflow-hidden">
        <div class="relative">
            <img :src="imageOf(space)" :alt="space.name" class="h-44 w-full object-cover" />
            <div class="absolute start-3 top-3">
                <Badge v-if="space.featured" tone="accent">{{ t('spaces.featured') }}</Badge>
            </div>
            <div class="absolute end-3 top-3">
                <Badge v-if="(space.active_discount_percent ?? 0) > 0" tone="danger">
                    {{ space.offer_label || t('spaces.discount', { n: space.active_discount_percent }) }}
                </Badge>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div>
                <h3 class="font-display text-lg font-semibold text-wz-fg">{{ space.name }}</h3>
                <p class="mt-1 text-sm text-wz-fg-muted">
                    {{ space.place?.city || space.place?.name || space.location }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs text-wz-fg-muted">
                <span class="rounded-lg bg-wz-muted px-2 py-1">
                    {{ t('spaces.capacity', { n: space.capacity }) }}
                </span>
                <span
                    v-for="a in (space.amenities || []).slice(0, 3)"
                    :key="a.id"
                    class="rounded-lg bg-wz-muted px-2 py-1"
                >
                    {{ a.name }}
                </span>
            </div>

            <div class="mt-auto flex items-end justify-between gap-3 pt-2">
                <div>
                    <p class="text-xs text-wz-fg-muted">
                        {{
                            space.booking_mode === 'seat'
                                ? t('spaces.priceUnitSeat')
                                : t('spaces.priceUnitWhole')
                        }}
                    </p>
                    <template v-if="(space.active_discount_percent ?? 0) > 0">
                        <div class="text-xs text-wz-fg-muted line-through">
                            ${{ Number(space.price_per_hour).toFixed(2) }}
                        </div>
                        <div class="text-lg font-semibold text-wz-brand">
                            ${{ Number(space.effective_price_per_hour).toFixed(2) }}
                        </div>
                    </template>
                    <template v-else>
                        <div class="text-lg font-semibold text-wz-fg">
                            ${{ Number(space.price_per_hour).toFixed(2) }}
                        </div>
                    </template>
                </div>

                <div class="flex gap-2">
                    <Link :href="route('spaces.show', space.id)">
                        <Button size="sm" variant="secondary">{{ t('spaces.details') }}</Button>
                    </Link>
                    <Link :href="route('user.bookings.create', { workspace_id: space.id })">
                        <Button size="sm">{{ t('spaces.book') }}</Button>
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>
