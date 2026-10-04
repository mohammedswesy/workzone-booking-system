<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
    broker: {
        type: String,
        default: 'users',
    },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    const action =
        props.broker === 'invitations' ? route('password.set.store') : route('password.store');
    form.post(action, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.resetPasswordTitle')" />

        <h1 class="mb-1 text-2xl font-semibold text-wz-fg">{{ t('auth.resetPasswordTitle') }}</h1>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                required
                autofocus
            >
                <template #label>{{ t('auth.email') }}</template>
            </Input>

            <Input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                required
            >
                <template #label>{{ t('auth.password') }}</template>
            </Input>

            <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                required
            >
                <template #label>{{ t('auth.confirmPassword') }}</template>
            </Input>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing">{{ t('auth.resetPassword') }}</Button>
            </div>
        </form>
    </GuestLayout>
</template>
