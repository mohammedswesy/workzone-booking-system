<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.$el?.querySelector('input')?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.$el?.querySelector('input')?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-semibold text-wz-fg">
                {{ t('profile.passwordTitle') }}
            </h2>
            <p class="mt-1 text-sm text-wz-fg-muted">
                {{ t('profile.passwordHint') }}
            </p>
        </header>

        <form class="mt-6 space-y-4" @submit.prevent="updatePassword">
            <Input
                id="current_password"
                ref="currentPasswordInput"
                v-model="form.current_password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.current_password"
            >
                <template #label>{{ t('profile.currentPassword') }}</template>
            </Input>

            <Input
                id="password"
                ref="passwordInput"
                v-model="form.password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
            >
                <template #label>{{ t('profile.newPassword') }}</template>
            </Input>

            <Input
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
            >
                <template #label>{{ t('auth.confirmPassword') }}</template>
            </Input>

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
