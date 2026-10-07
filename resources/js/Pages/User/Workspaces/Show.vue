<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import WorkspaceCover from '@/Components/Ui/WorkspaceCover.vue';
import { workspaceCoverUrl } from '@/utils/workspaceImage';

const props = defineProps({
    workspace: { type: Object, required: true },
    venue: { type: Object, default: null },
    units: { type: Array, default: () => [] },
    can_book: { type: Boolean, default: false },
    pending_booking_id: { type: Number, default: null },
});

const { t } = useI18n();

const gallery = computed(() => {
    const list = props.workspace.images || [];
    if (list.length) return list;
    const cover = workspaceCoverUrl(props.workspace);
    if (cover) {
        return [{ id: 'legacy', url: cover }];
    }
    return [];
});

function book() {
    router.visit(route('user.bookings.create', { workspace_id: props.workspace.id }));
}
</script>

<template>
    <AppLayout :title="workspace.name">
        <Head :title="workspace.name" />

        <PageHeader :title="workspace.name">
            <template #subtitle>
                {{ workspace.place?.city || workspace.place?.name || workspace.location }}
            </template>
            <template #actions>
                <Badge v-if="(workspace.active_discount_percent ?? 0) > 0" tone="danger">
                    {{ workspace.offer_label || t('spaces.discount', { n: workspace.active_discount_percent }) }}
                </Badge>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <div class="wz-surface overflow-hidden">
                    <WorkspaceCover
                        :space="workspace"
                        img-class="h-72 w-full object-cover sm:h-96"
                    />
                    <div v-if="gallery.length > 1" class="grid grid-cols-4 gap-2 p-3">
                        <img
                            v-for="img in gallery.slice(0, 4)"
                            :key="img.id"
                            :src="img.url"
                            class="h-20 w-full rounded-lg object-cover"
                            :alt="t('spaces.gallery')"
                        />
                    </div>
                </div>

                <section class="wz-surface p-5">
                    <h2 class="mb-2 font-semibold text-wz-fg">{{ t('spaces.about') }}</h2>
                    <p class="text-sm leading-relaxed text-wz-fg-muted">
                        {{ workspace.description || t('spaces.subtitle') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Badge tone="neutral">{{ t('spaces.capacity', { n: workspace.capacity }) }}</Badge>
                        <Badge
                            v-for="a in workspace.amenities || []"
                            :key="a.id"
                            tone="brand"
                        >
                            {{ a.name }}
                        </Badge>
                    </div>
                </section>

                <section v-if="workspace.place" class="wz-surface p-5">
                    <h2 class="mb-2 font-semibold">{{ t('spaces.location') }}</h2>
                    <p class="text-sm text-wz-fg-muted">
                        {{ workspace.place.name }}
                        <span v-if="workspace.place.address"> · {{ workspace.place.address }}</span>
                        <span v-if="workspace.place.city"> · {{ workspace.place.city }}</span>
                    </p>
                </section>
            </div>

            <aside class="wz-surface sticky top-24 h-fit space-y-4 p-5">
                <div>
                    <div class="text-sm text-wz-fg-muted">
                        {{
                            workspace.booking_mode === 'seat'
                                ? t('spaces.priceUnitSeat')
                                : t('spaces.priceUnitWhole')
                        }}
                    </div>
                    <template v-if="(workspace.active_discount_percent ?? 0) > 0">
                        <div class="text-sm text-wz-fg-muted line-through">
                            ${{ Number(workspace.price_per_hour).toFixed(2) }}
                        </div>
                        <div class="text-3xl font-semibold text-wz-brand">
                            ${{ Number(workspace.effective_price_per_hour).toFixed(2) }}
                        </div>
                        <Badge tone="accent" class="mt-2">{{ t('spaces.offer') }}</Badge>
                    </template>
                    <template v-else>
                        <div class="text-3xl font-semibold text-wz-fg">
                            ${{ Number(workspace.price_per_hour).toFixed(2) }}
                        </div>
                    </template>
                </div>

                <p class="text-xs text-wz-fg-muted">
                    {{ t('spaces.hours', { open: workspace.opening_time, close: workspace.closing_time }) }}
                </p>

                <div class="grid gap-2">
                    <Button v-if="can_book && !pending_booking_id" block size="lg" @click="book">
                        {{ t('spaces.bookCta') }}
                    </Button>
                    <Link v-else-if="pending_booking_id" :href="route('user.bookings.show', pending_booking_id)">
                        <Button block variant="secondary">{{ t('spaces.pendingBooking') }}</Button>
                    </Link>
                    <Link v-else :href="route('login')">
                        <Button block variant="secondary">{{ t('nav.login') }}</Button>
                    </Link>
                    <Link :href="route('spaces.index')">
                        <Button block variant="ghost">{{ t('spaces.backToSpaces') }}</Button>
                    </Link>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
