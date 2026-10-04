<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    status: String,
});

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => form.post(route('password.email'));
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.forgot')" />

        <h1 class="mb-1 text-2xl font-semibold">{{ t('auth.forgot') }}</h1>
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
            <Button type="submit" :disabled="form.processing">{{ t('auth.forgot') }}</Button>
        </form>
    </GuestLayout>
</template>
