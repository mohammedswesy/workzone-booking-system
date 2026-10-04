<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: {
        type: String,
    },
});

const { t } = useI18n();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head :title="t('auth.verifyEmailTitle')" />

        <h1 class="mb-1 text-2xl font-semibold text-wz-fg">{{ t('auth.verifyEmailTitle') }}</h1>
        <p class="mb-6 text-sm text-wz-fg-muted">{{ t('auth.verifyEmailHint') }}</p>

        <p v-if="verificationLinkSent" class="mb-4 text-sm font-medium text-wz-success">
            {{ t('auth.verificationLinkSent') }}
        </p>

        <form @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ t('auth.resendVerificationEmail') }}
                </Button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="wz-focus text-sm text-wz-brand hover:underline"
                >
                    {{ t('auth.logOut') }}
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
