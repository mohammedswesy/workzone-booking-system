<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import VenueFormFields from '@/Components/Venues/VenueFormFields.vue';

defineProps({
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    cityCenters: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const form = useForm({
    name: '',
    slug: '',
    description: '',
    location_id: null,
    address: '',
    lat: null,
    lng: null,
    timezone: 'Asia/Gaza',
    status: 'draft',
    featured: false,
    amenities: [],
});

function submit() {
    form.post(route('owner.venues.store'));
}
</script>

<template>
    <AppLayout :title="t('venues.createTitle')">
        <Head :title="t('venues.createTitle')" />
        <PageHeader :title="t('venues.createTitle')" :subtitle="t('venues.createHint')">
            <template #actions>
                <Link :href="route('owner.venues.index')"><Button variant="secondary">{{ t('common.back') }}</Button></Link>
            </template>
        </PageHeader>
        <form class="wz-surface mx-auto max-w-3xl space-y-5 p-5" @submit.prevent="submit">
            <VenueFormFields
                :form="form"
                :locations="locations"
                :amenities="amenities"
                :city-centers="cityCenters"
                id-prefix="owner-venue"
            />
            <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
        </form>
    </AppLayout>
</template>
