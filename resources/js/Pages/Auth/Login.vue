<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.login')" />

        <h1 class="mb-1 text-2xl font-semibold text-wz-fg">{{ t('auth.welcome') }}</h1>
        <p class="mb-6 text-sm text-wz-fg-muted">{{ t('auth.welcomeHint') }}</p>

        <div v-if="status" class="mb-4 text-sm font-medium text-wz-success">{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
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
                autocomplete="current-password"
                :error="form.errors.password"
                required
            >
                <template #label>{{ t('auth.password') }}</template>
            </Input>

            <label class="flex items-center gap-2 text-sm text-wz-fg">
                <Checkbox v-model:checked="form.remember" name="remember" />
                {{ t('auth.remember') }}
            </label>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-wz-brand hover:underline"
                >
                    {{ t('auth.forgot') }}
                </Link>
                <Button type="submit" :disabled="form.processing">{{ t('auth.login') }}</Button>
            </div>

            <p class="pt-2 text-sm text-wz-fg-muted">
                {{ t('auth.noAccount') }}
                <Link :href="route('register')" class="font-medium text-wz-brand hover:underline">
                    {{ t('auth.register') }}
                </Link>
            </p>
            <InputError :message="form.errors.email" class="hidden" />
        </form>
    </GuestLayout>
</template>
