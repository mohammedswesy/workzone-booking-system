<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import WorkspaceFormFields from '@/Components/Workspaces/WorkspaceFormFields.vue';

defineProps({
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    owner_id: null,
    name: '',
    description: '',
    location: '',
    location_id: null,
    capacity: 1,
    booking_mode: 'seat',
    price_per_hour: 0,
    opening_time: '08:00',
    closing_time: '22:00',
    status: 'published',
    featured: false,
    payment_instructions: '',
    payment_methods: ['bank_transfer'],
    amenities: [],
    images: [],
});

function onFiles(files) {
    form.images = files;
}

function submit() {
    form.post(route('admin.workspaces.store'), { forceFormData: true });
}
</script>

<template>
    <AppLayout :title="t('admin.createWorkspace')">
        <Head :title="t('admin.createWorkspace')" />

        <PageHeader :title="t('admin.createWorkspace')" :subtitle="t('admin.createWorkspaceHint')">
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
                show-owner-select
                id-prefix="admin-create"
                @files="onFiles"
            />

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('admin.workspaces.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
