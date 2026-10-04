<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.confirmPasswordTitle')" />

        <h1 class="mb-1 text-2xl font-semibold text-wz-fg">{{ t('auth.confirmPasswordTitle') }}</h1>
        <p class="mb-6 text-sm text-wz-fg-muted">{{ t('auth.confirmPasswordHint') }}</p>

        <form class="space-y-4" @submit.prevent="submit">
            <Input
                id="password"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
                autofocus
            >
                <template #label>{{ t('auth.password') }}</template>
            </Input>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing">{{ t('auth.confirm') }}</Button>
            </div>
        </form>
    </GuestLayout>
</template>
