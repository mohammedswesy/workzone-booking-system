<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const { t } = useI18n();

const form = useForm({
    code: '',
});

function submit() {
    form.post(route('admin.two-factor.verify'));
}
</script>

<template>
    <AppLayout :title="t('admin.twoFactorChallengeTitle')">
        <Head :title="t('admin.twoFactorChallengeTitle')" />

        <PageHeader
            :title="t('admin.twoFactorChallengeTitle')"
            :subtitle="t('admin.twoFactorChallengeHint')"
        />

        <section class="wz-surface max-w-md space-y-4 p-5">
            <form class="space-y-3" @submit.prevent="submit">
                <Input
                    id="code"
                    v-model="form.code"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    :error="form.errors.code"
                >
                    <template #label>{{ t('admin.twoFactorCode') }}</template>
                </Input>
                <p class="text-xs text-wz-fg-muted">{{ t('admin.twoFactorChallengeRecovery') }}</p>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('admin.twoFactorVerify') }}
                </Button>
            </form>
        </section>
    </AppLayout>
</template>
