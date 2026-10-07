<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import UnitFormFields from '@/Components/Venues/UnitFormFields.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    workspace: { type: Object, required: true },
    bookingModeLocked: { type: Boolean, default: false },
    amenities: { type: Array, default: () => [] },
});

const { t } = useI18n();
const form = useForm({
    name: props.workspace.name,
    type: props.workspace.type?.value || props.workspace.type || 'other',
    booking_mode: props.workspace.booking_mode?.value || props.workspace.booking_mode || 'seat',
    capacity: props.workspace.capacity,
    price_per_hour: props.workspace.price_per_hour,
    opening_time: String(props.workspace.opening_time || '08:00').slice(0, 5),
    closing_time: String(props.workspace.closing_time || '22:00').slice(0, 5),
    status: props.workspace.status?.value || props.workspace.status,
    amenities: (props.workspace.amenities || []).map((a) => a.id),
    images: [],
});

function onFiles(files) {
    form.images = files;
}

function submit() {
    form.put(route('admin.venues.units.update', [props.venue.slug, props.workspace.id]), {
        forceFormData: true,
    });
}
</script>

<template>
    <AppLayout :title="workspace.name">
        <Head :title="workspace.name" />
        <PageHeader :title="workspace.name" :subtitle="venue.name">
            <template #actions>
                <Link :href="route('admin.venues.show', venue.slug)">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>
        <form class="wz-surface mx-auto max-w-3xl space-y-5 p-5" @submit.prevent="submit">
            <UnitFormFields
                :form="form"
                :amenities="amenities"
                :workspace="workspace"
                :booking-mode-locked="bookingModeLocked"
                id-prefix="owner-unit-edit"
                @files="onFiles"
            />
            <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
        </form>
    </AppLayout>
</template>
