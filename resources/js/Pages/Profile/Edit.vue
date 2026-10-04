<script setup>
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();
</script>

<template>
    <AppLayout :title="t('profile.title')">
        <Head :title="t('profile.title')" />

        <PageHeader :title="t('profile.title')" />

        <div class="mx-auto max-w-xl space-y-6">
            <section class="wz-surface p-4 sm:p-6">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </section>

            <section class="wz-surface p-4 sm:p-6">
                <UpdatePasswordForm />
            </section>

            <section class="wz-surface p-4 sm:p-6">
                <DeleteUserForm />
            </section>
        </div>
    </AppLayout>
</template>
