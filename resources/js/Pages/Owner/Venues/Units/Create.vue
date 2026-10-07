<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import UnitFormFields from '@/Components/Venues/UnitFormFields.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    amenities: { type: Array, default: () => [] },
});

const { t } = useI18n();
const form = useForm({
    name: '',
    type: 'hot_desk',
    booking_mode: 'seat',
    capacity: 1,
    price_per_hour: 0,
    opening_time: '08:00',
    closing_time: '22:00',
    status: 'published',
    amenities: [],
    images: [],
});

function onFiles(files) {
    form.images = files;
}

function submit() {
    form.post(route('owner.venues.units.store', props.venue.slug), { forceFormData: true });
}
</script>

<template>
    <AppLayout :title="t('venues.addUnit')">
        <Head :title="t('venues.addUnit')" />
        <PageHeader :title="t('venues.addUnit')" :subtitle="venue.name">
            <template #actions>
                <Link :href="route('owner.venues.show', venue.slug)">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>
        <form class="wz-surface mx-auto max-w-3xl space-y-5 p-5" @submit.prevent="submit">
            <UnitFormFields :form="form" :amenities="amenities" id-prefix="owner-unit" @files="onFiles" />
            <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
        </form>
    </AppLayout>
</template>
