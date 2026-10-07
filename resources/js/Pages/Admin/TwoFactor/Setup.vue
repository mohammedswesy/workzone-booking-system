<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';

const props = defineProps({
    secret: String,
    otpauthUrl: String,
    recoveryCodes: { type: Array, default: () => [] },
    confirmed: { type: Boolean, default: false },
});

const { t } = useI18n();

const form = useForm({
    code: '',
});

function submit() {
    form.post(route('admin.two-factor.confirm'));
}
</script>

<template>
    <AppLayout :title="t('admin.twoFactorSetupTitle')">
        <Head :title="t('admin.twoFactorSetupTitle')" />

        <PageHeader
            :title="t('admin.twoFactorSetupTitle')"
            :subtitle="t('admin.twoFactorSetupHint')"
        >
            <template #actions>
                <Badge :tone="confirmed ? 'success' : 'warning'">
                    {{ confirmed ? t('admin.twoFactorEnabled') : t('admin.twoFactorPending') }}
                </Badge>
            </template>
        </PageHeader>

        <section class="wz-surface max-w-xl space-y-4 p-5">
            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.twoFactorSecret') }}</div>
                <code class="mt-1 block break-all rounded-xl bg-wz-muted px-3 py-2 text-sm">{{ secret }}</code>
            </div>

            <div>
                <div class="text-sm text-wz-fg-muted">{{ t('admin.twoFactorOtpauth') }}</div>
                <a
                    :href="otpauthUrl"
                    class="mt-1 inline-flex break-all text-sm font-medium text-wz-brand hover:underline"
                >
                    {{ otpauthUrl }}
                </a>
            </div>

            <div v-if="recoveryCodes?.length">
                <div class="text-sm font-medium text-wz-fg">{{ t('admin.twoFactorRecoveryCodes') }}</div>
                <p class="mt-1 text-sm text-wz-fg-muted">{{ t('admin.twoFactorRecoveryHint') }}</p>
                <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                    <li
                        v-for="code in recoveryCodes"
                        :key="code"
                        class="rounded-lg bg-wz-muted px-3 py-1.5 font-mono text-sm"
                    >
                        {{ code }}
                    </li>
                </ul>
            </div>

            <form v-if="!confirmed" class="space-y-3 border-t border-wz-border pt-4" @submit.prevent="submit">
                <Input
                    id="code"
                    v-model="form.code"
                    autocomplete="one-time-code"
                    required
                    :error="form.errors.code"
                >
                    <template #label>{{ t('admin.twoFactorCode') }}</template>
                </Input>
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('admin.twoFactorConfirm') }}
                </Button>
            </form>

            <p v-else class="text-sm text-wz-fg-muted">{{ t('admin.twoFactorAlreadyEnabled') }}</p>
        </section>
    </AppLayout>
</template>
