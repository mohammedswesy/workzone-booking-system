<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

defineProps({
    mailDeliverable: { type: Boolean, default: false },
});

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'owner',
});

function submit() {
    form.post(route('admin.users.store'));
}
</script>

<template>
    <AppLayout :title="t('admin.createUser')">
        <Head :title="t('admin.createUser')" />

        <PageHeader :title="t('admin.createUser')" :subtitle="t('admin.createUserHint')">
            <template #actions>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <p class="rounded-xl bg-wz-muted px-3 py-2 text-sm text-wz-fg-muted">
                {{ mailDeliverable ? t('admin.inviteWillEmail') : t('admin.inviteLinkHint') }}
            </p>

            <Input id="create-name" v-model="form.name" :error="form.errors.name" :disabled="form.processing">
                <template #label>{{ t('admin.name') }}</template>
            </Input>
            <Input
                id="create-email"
                v-model="form.email"
                type="email"
                :error="form.errors.email"
                :disabled="form.processing"
            >
                <template #label>{{ t('admin.email') }}</template>
            </Input>
            <Input
                id="create-phone"
                v-model="form.phone"
                :error="form.errors.phone"
                :disabled="form.processing"
            >
                <template #label>{{ t('admin.phone') }}</template>
            </Input>
            <Select
                id="create-role"
                v-model="form.role"
                :error="form.errors.role"
                :disabled="form.processing"
            >
                <template #label>{{ t('common.role') }}</template>
                <option value="owner">{{ t('admin.roleOwner') }}</option>
                <option value="user">{{ t('admin.roleUser') }}</option>
                <option value="admin">{{ t('admin.roleAdmin') }}</option>
            </Select>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('admin.users.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
