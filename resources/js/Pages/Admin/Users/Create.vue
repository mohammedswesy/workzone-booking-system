<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import CreateOwnerFormFields from '@/Components/Admin/CreateOwnerFormFields.vue';
import OwnerCreatedCredentialsPanel from '@/Components/Admin/OwnerCreatedCredentialsPanel.vue';

const props = defineProps({
    mailDeliverable: { type: Boolean, default: false },
    created: { type: Object, default: null },
});

const { t } = useI18n();
const created = ref(props.created);
const formError = ref('');
const busy = ref(false);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    send_invitation: false,
    must_change_password: true,
});

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
</script>

<template>
    <AppLayout :title="t('admin.createOwner')">
        <Head :title="t('admin.createOwner')" />

        <PageHeader :title="t('admin.createOwner')" :subtitle="t('admin.createOwnerHint')">
            <template #actions>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="mx-auto max-w-xl space-y-4">
            <OwnerCreatedCredentialsPanel
                v-if="created"
                :email="created.email"
                :plain-password="created.plain_password"
                :invitation="created.invitation"
            />

            <form v-if="!created" class="wz-surface space-y-4 p-5" @submit.prevent="submit">
                <p class="rounded-xl bg-wz-muted px-3 py-2 text-sm text-wz-fg-muted">
                    {{ t('admin.createOwnerRoleNote') }}
                </p>

                <CreateOwnerFormFields
                    :form="form"
                    id-prefix="create-owner"
                    :mail-deliverable="mailDeliverable"
                    :disabled="busy"
                />

                <p v-if="formError" class="text-sm text-wz-danger">{{ formError }}</p>

                <div class="flex flex-wrap gap-3">
                    <Button type="submit" variant="primary" :disabled="busy">
                        {{ t('common.save') }}
                    </Button>
                    <Link :href="route('admin.users.index')">
                        <Button variant="ghost" :disabled="busy">{{ t('common.cancel') }}</Button>
                    </Link>
                </div>
            </form>

            <div v-else class="flex flex-wrap gap-3">
                <Link :href="route('admin.users.create')">
                    <Button variant="primary">{{ t('admin.createAnotherOwner') }}</Button>
                </Link>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
