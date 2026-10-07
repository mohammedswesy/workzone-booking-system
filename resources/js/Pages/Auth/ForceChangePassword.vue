<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put(route('password.force.update'), {
        onFinish: () => form.reset('password', 'password_confirmation', 'current_password'),
    });
}
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.forceChangeTitle')" />

        <div class="mb-4 space-y-2 text-center">
            <h1 class="font-display text-2xl font-semibold text-wz-fg">{{ t('auth.forceChangeTitle') }}</h1>
            <p class="text-sm text-wz-fg-muted">{{ t('auth.forceChangeHint') }}</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <Input
                id="force-current-password"
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.current_password"
                :disabled="form.processing"
            >
                <template #label>{{ t('profile.currentPassword') }}</template>
            </Input>
            <Input
                id="force-password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                :disabled="form.processing"
            >
                <template #label>{{ t('profile.newPassword') }}</template>
            </Input>
            <Input
                id="force-password-confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                :disabled="form.processing"
            >
                <template #label>{{ t('auth.confirmPassword') }}</template>
            </Input>

            <Button type="submit" class="w-full" variant="primary" :disabled="form.processing">
                {{ t('auth.updatePassword') }}
            </Button>
        </form>

        <div class="mt-4 text-center">
            <Link :href="route('logout')" method="post" as="button" class="text-sm text-wz-fg-muted underline">
                {{ t('nav.logout') }}
            </Link>
        </div>
    </GuestLayout>
</template>
