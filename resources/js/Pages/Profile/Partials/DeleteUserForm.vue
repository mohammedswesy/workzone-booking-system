<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const confirmingUserDeletion = ref(false);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header>
            <h2 class="text-lg font-semibold text-wz-fg">
                {{ t('profile.deleteTitle') }}
            </h2>
            <p class="mt-1 text-sm text-wz-fg-muted">
                {{ t('profile.deleteHint') }}
            </p>
        </header>

        <Button variant="danger" @click="confirmUserDeletion">
            {{ t('profile.deleteAccount') }}
        </Button>

        <Modal
            :show="confirmingUserDeletion"
            :title="t('profile.deleteConfirmTitle')"
            @close="closeModal"
        >
            <p class="text-sm text-wz-fg-muted">
                {{ t('profile.deleteConfirmMessage') }}
            </p>
            <Input
                id="delete-password"
                v-model="form.password"
                class="mt-4"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                @keyup.enter="deleteUser"
            >
                <template #label>{{ t('auth.password') }}</template>
            </Input>
            <template #footer>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Button variant="secondary" @click="closeModal">{{ t('common.cancel') }}</Button>
                    <Button variant="danger" :disabled="form.processing" @click="deleteUser">
                        {{ t('profile.deleteConfirmAction') }}
                    </Button>
                </div>
            </template>
        </Modal>
    </section>
</template>
