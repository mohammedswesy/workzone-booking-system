<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({ user: Object });

const { t } = useI18n();

const form = useForm({
    role: props.user.role ?? 'user',
});

function submit() {
    form.put(route('admin.users.update', props.user.id));
}
</script>

<template>
    <AppLayout :title="t('admin.editUser')">
        <Head :title="t('admin.editUser')" />

        <PageHeader :title="t('admin.editUser')" :subtitle="user.email">
            <template #actions>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <Input id="admin-user-name" :model-value="user.name" disabled>
                <template #label>{{ t('admin.name') }}</template>
            </Input>
            <Input id="admin-user-email" :model-value="user.email" disabled>
                <template #label>{{ t('admin.email') }}</template>
            </Input>

            <Select
                id="admin-user-role"
                v-model="form.role"
                :error="form.errors.role"
                :disabled="form.processing"
            >
                <template #label>{{ t('common.role') }}</template>
                <option value="user">{{ t('admin.roleUser') }}</option>
                <option value="owner">{{ t('admin.roleOwner') }}</option>
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
