<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const { t } = useI18n();
const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-wz-fg">
                {{ t('profile.informationTitle') }}
            </h2>
            <p class="mt-1 text-sm text-wz-fg-muted">
                {{ t('profile.informationHint') }}
            </p>
        </header>

        <form class="mt-6 space-y-4" @submit.prevent="form.patch(route('profile.update'))">
            <Input
                id="name"
                v-model="form.name"
                autocomplete="name"
                :error="form.errors.name"
                required
            >
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

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-sm text-wz-fg">
                    {{ t('profile.emailUnverified') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="wz-focus ms-1 font-medium text-wz-brand hover:underline"
                    >
                        {{ t('profile.resendVerification') }}
                    </Link>
                </p>

                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-wz-success"
                >
                    {{ t('profile.verificationSent') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-4 pt-2">
                <Button type="submit" :disabled="form.processing">{{ t('common.save') }}</Button>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm text-wz-fg-muted">
                        {{ t('profile.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
