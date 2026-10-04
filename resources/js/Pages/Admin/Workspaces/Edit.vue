<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({ workspace: Object });

const { t } = useI18n();

const form = useForm({
    name: props.workspace.name,
    location: props.workspace.location,
    capacity: props.workspace.capacity,
    price_per_hour: props.workspace.price_per_hour,
});

function submit() {
    form.put(route('admin.workspaces.update', props.workspace.id));
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

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <Input id="admin-ws-name" v-model="form.name" :error="form.errors.name" :disabled="form.processing">
                <template #label>{{ t('bookings.workspace') }}</template>
            </Input>
            <Input
                id="admin-ws-location"
                v-model="form.location"
                :error="form.errors.location"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.locationText') }}</template>
            </Input>
            <div class="grid gap-3 sm:grid-cols-2">
                <Input
                    id="admin-ws-capacity"
                    v-model="form.capacity"
                    type="number"
                    :error="form.errors.capacity"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.capacity') }}</template>
                </Input>
                <Input
                    id="admin-ws-price"
                    v-model="form.price_per_hour"
                    type="number"
                    :error="form.errors.price_per_hour"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.pricePerHour') }}</template>
                </Input>
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
