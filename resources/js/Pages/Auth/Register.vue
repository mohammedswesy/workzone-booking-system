<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.register')" />

        <h1 class="mb-1 text-2xl font-semibold text-wz-fg">{{ t('auth.registerTitle') }}</h1>
        <p class="mb-6 text-sm text-wz-fg-muted">{{ t('auth.registerHint') }}</p>

        <form class="space-y-4" @submit.prevent="submit">
            <Input id="name" v-model="form.name" autocomplete="name" :error="form.errors.name" required>
                <template #label>{{ t('auth.name') }}</template>
            </Input>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                required
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

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <Link :href="route('login')" class="text-sm text-wz-brand hover:underline">
                    {{ t('auth.alreadyRegistered') }}
                </Link>
                <Button type="submit" :disabled="form.processing">{{ t('auth.register') }}</Button>
            </div>
        </form>
    </GuestLayout>
</template>
