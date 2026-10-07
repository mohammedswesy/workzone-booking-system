<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import CreateOwnerFormFields from '@/Components/Admin/CreateOwnerFormFields.vue';
import OwnerCreatedCredentialsPanel from '@/Components/Admin/OwnerCreatedCredentialsPanel.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mailDeliverable: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'created']);

const { t } = useI18n();
const formError = ref('');
const busy = ref(false);
const created = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    send_invitation: false,
    must_change_password: true,
});

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.clearErrors();
            form.reset();
            form.send_invitation = false;
            form.must_change_password = true;
            formError.value = '';
            busy.value = false;
            created.value = null;
        }
    },
);

async function submit() {
    formError.value = '';
    form.clearErrors();
    busy.value = true;

    try {
        const payload = {
            name: form.name,
            email: form.email,
            phone: form.phone || null,
            send_invitation: form.send_invitation,
        };
        if (!form.send_invitation) {
            payload.password = form.password;
            payload.password_confirmation = form.password_confirmation;
            payload.must_change_password = form.must_change_password;
        }

        const { data } = await axios.post(route('admin.owners.store'), payload, {
            headers: { Accept: 'application/json' },
        });

        form.reset();
        form.send_invitation = false;
        form.must_change_password = true;

        created.value = {
            email: data.credentials?.email || data.owner?.email || '',
            plain_password: data.credentials?.plain_password ?? null,
            invitation: data.invitation ?? null,
        };

        emit('created', data.owner);
    } catch (error) {
        const errors = error?.response?.data?.errors;
        if (errors) {
            Object.entries(errors).forEach(([key, messages]) => {
                form.setError(key, Array.isArray(messages) ? messages[0] : String(messages));
            });
        } else {
            formError.value = t('admin.createOwnerFailed');
        }
    } finally {
        busy.value = false;
    }
}

function close() {
    if (!busy.value) {
        created.value = null;
        emit('close');
    }
}
</script>

<template>
    <Modal
        :show="show"
        :title="created ? t('admin.ownerCreatedTitle') : t('admin.newOwner')"
        @close="close"
    >
        <OwnerCreatedCredentialsPanel
            v-if="created"
            :email="created.email"
            :plain-password="created.plain_password"
            :invitation="created.invitation"
        />

        <form v-else class="space-y-4" @submit.prevent="submit">
            <CreateOwnerFormFields
                :form="form"
                id-prefix="modal-owner"
                :mail-deliverable="mailDeliverable"
                :disabled="busy"
            />
            <p v-if="formError" class="text-sm text-wz-danger">{{ formError }}</p>
        </form>

        <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
                <template v-if="created">
                    <Button type="button" variant="primary" @click="close">
                        {{ t('common.done') }}
                    </Button>
                </template>
                <template v-else>
                    <Button type="button" variant="ghost" :disabled="busy" @click="close">
                        {{ t('common.cancel') }}
                    </Button>
                    <Button type="button" variant="primary" :disabled="busy" @click="submit">
                        {{ t('admin.createOwner') }}
                    </Button>
                </template>
            </div>
        </template>
    </Modal>
</template>
