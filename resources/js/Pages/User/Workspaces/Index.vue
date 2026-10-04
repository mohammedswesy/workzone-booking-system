<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import WorkspaceCard from '@/Components/Ui/WorkspaceCard.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/Components/Ui/EmptyState.vue';
import LoadingState from '@/Components/Ui/LoadingState.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
    spaces: Object,
    filters: Object,
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
});

const { t } = useI18n();
const drawerOpen = ref(false);
const filterLoading = ref(false);

onMounted(() => {
    const onStart = () => {
        filterLoading.value = true;
    };
    const onStop = () => {
        filterLoading.value = false;
    };
    router.on('start', onStart);
    router.on('finish', onStop);
    router.on('error', onStop);
    onUnmounted(() => {
        router.off('start', onStart);
        router.off('finish', onStop);
        router.off('error', onStop);
    });
});

const form = reactive({
    search: props.filters?.search || '',
    city: props.filters?.city || '',
    min_price: props.filters?.min_price || '',
    max_price: props.filters?.max_price || '',
    capacity: props.filters?.capacity || '',
    featured: Boolean(props.filters?.featured),
    amenities: String(props.filters?.amenities || '')
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean),
});

const items = computed(() => props.spaces?.data ?? []);

function apply() {
    router.get(
        route('spaces.index'),
        {
            search: form.search || undefined,
            city: form.city || undefined,
            min_price: form.min_price || undefined,
            max_price: form.max_price || undefined,
            capacity: form.capacity || undefined,
            featured: form.featured ? 1 : undefined,
            amenities: form.amenities.length ? form.amenities.join(',') : undefined,
        },
        { preserveState: true, replace: true },
    );
    drawerOpen.value = false;
}

function reset() {
    form.search = '';
    form.city = '';
    form.min_price = '';
    form.max_price = '';
    form.capacity = '';
    form.featured = false;
    form.amenities = [];
    apply();
}

function toggleAmenity(id) {
    const key = String(id);
    if (form.amenities.includes(key)) {
        form.amenities = form.amenities.filter((a) => a !== key);
    } else {
        form.amenities.push(key);
    }
}
</script>

<template>
    <AppLayout :title="t('spaces.title')">
        <Head :title="t('spaces.title')" />

        <PageHeader :title="t('spaces.title')" :subtitle="t('spaces.subtitle')">
            <template #actions>
                <Button class="md:hidden" variant="secondary" @click="drawerOpen = true">
                    {{ t('spaces.filters') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
            <!-- Desktop filters -->
            <aside class="wz-surface hidden h-fit p-4 md:block">
                <h2 class="mb-3 text-sm font-semibold text-wz-fg">{{ t('spaces.filters') }}</h2>
                <form class="grid gap-3" @submit.prevent="apply">
                    <Input v-model="form.search" :placeholder="t('spaces.search')" />
                    <Input v-model="form.city" :placeholder="t('spaces.city')" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="form.min_price" type="number" :placeholder="t('spaces.minPrice')" />
                        <Input v-model="form.max_price" type="number" :placeholder="t('spaces.maxPrice')" />
                    </div>
                    <Input v-model="form.capacity" type="number" :placeholder="t('spaces.capacity', { n: '' }).trim()" />
                    <label class="flex items-center gap-2 text-sm text-wz-fg">
                        <input v-model="form.featured" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('spaces.onlyFeatured') }}
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
                    <div class="flex gap-2 pt-1">
                        <Button type="submit" class="flex-1">{{ t('spaces.apply') }}</Button>
                        <Button type="button" variant="secondary" @click="reset">{{ t('spaces.reset') }}</Button>
                    </div>
                </form>
            </aside>

            <div class="min-w-0">
                <LoadingState v-if="filterLoading" />
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

                <Pagination v-if="spaces?.links" :links="spaces.links" />
            </div>
        </div>

        <!-- Mobile filter drawer -->
        <div
            v-if="drawerOpen"
            class="fixed inset-0 z-40 bg-black/40 md:hidden"
            @click.self="drawerOpen = false"
        >
            <div class="absolute inset-y-0 end-0 w-[min(100%,22rem)] overflow-y-auto bg-wz-elevated p-4 shadow-wz">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold">{{ t('spaces.filters') }}</h2>
                    <Button size="sm" variant="ghost" @click="drawerOpen = false">{{ t('common.close') }}</Button>
                </div>
                <form class="grid gap-3" @submit.prevent="apply">
                    <Input v-model="form.search" :placeholder="t('spaces.search')" />
                    <Input v-model="form.city" :placeholder="t('spaces.city')" />
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="form.min_price" type="number" :placeholder="t('spaces.minPrice')" />
                        <Input v-model="form.max_price" type="number" :placeholder="t('spaces.maxPrice')" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.featured" type="checkbox" class="rounded border-wz-border text-wz-brand" />
                        {{ t('spaces.onlyFeatured') }}
                    </label>
                    <Button type="submit">{{ t('spaces.apply') }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
