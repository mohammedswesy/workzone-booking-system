<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import WorkspaceFormFields from '@/Components/Workspaces/WorkspaceFormFields.vue';

const props = defineProps({
    workspace: { type: Object, required: true },
    needsPaymentSetup: { type: Boolean, default: false },
    bookingModeLocked: { type: Boolean, default: false },
    hasBookings: { type: Boolean, default: false },
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
});

const { t } = useI18n();

function timeValue(value) {
    if (!value) return '';
    return String(value).slice(0, 5);
}

const form = useForm({
    owner_id: props.workspace.owner_id ?? null,
    name: props.workspace.name ?? '',
    description: props.workspace.description ?? '',
    location: props.workspace.location ?? '',
    location_id: props.workspace.location_id ?? null,
    capacity: props.workspace.capacity ?? 1,
    booking_mode: props.workspace.booking_mode ?? 'seat',
    price_per_hour: props.workspace.price_per_hour ?? 0,
    opening_time: timeValue(props.workspace.opening_time) || '08:00',
    closing_time: timeValue(props.workspace.closing_time) || '22:00',
    status: props.workspace.status ?? 'published',
    featured: Boolean(props.workspace.featured),
    payment_instructions: props.workspace.payment_instructions ?? '',
    payment_methods: [...(props.workspace.payment_methods || [])],
    amenities: (props.workspace.amenities || []).map((a) => a.id),
    images: [],
});

function onFiles(files) {
    form.images = files;
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.workspaces.update', props.workspace.id), {
            forceFormData: true,
            preserveScroll: true,
        });
}

function archiveOrDelete() {
    if (!confirm(props.hasBookings ? t('admin.archiveWorkspaceConfirm') : t('admin.deleteWorkspaceConfirm'))) {
        return;
    }
    router.delete(route('admin.workspaces.destroy', props.workspace.id));
}
</script>

<template>
    <AppLayout :title="t('owner.editWorkspace')">
        <Head :title="t('owner.editWorkspace')" />

        <PageHeader :title="t('owner.editWorkspace')" :subtitle="workspace.name">
            <template #actions>
                <Link :href="route('admin.workspaces.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-3xl space-y-5 p-5" @submit.prevent="submit">
            <WorkspaceFormFields
                :form="form"
                :locations="locations"
                :amenities="amenities"
                :owners="owners"
                :workspace="workspace"
                :needs-payment-setup="needsPaymentSetup"
                :booking-mode-locked="bookingModeLocked"
                show-owner-select
                id-prefix="admin-edit"
                @files="onFiles"
            />

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Button type="button" variant="danger" :disabled="form.processing" @click="archiveOrDelete">
                    {{ hasBookings ? t('admin.archiveWorkspace') : t('common.delete') }}
                </Button>
                <Link :href="route('admin.workspaces.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
