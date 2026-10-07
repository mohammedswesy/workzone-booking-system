<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Badge from '@/Components/Ui/Badge.vue';

const props = defineProps({
    methods: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    commissionPercent: { type: [String, Number], default: '0' },
});

const { t } = useI18n();

const settingsForm = useForm({
    commission_percent: props.commissionPercent,
});

const createForm = useForm({
    type: 'bank_transfer',
    label: '',
    account_holder: '',
    account_identifier: '',
    note: '',
    sort_order: 0,
    is_active: true,
    qr: null,
});

const editId = ref(null);
const editForm = useForm({
    type: 'bank_transfer',
    label: '',
    account_holder: '',
    account_identifier: '',
    note: '',
    sort_order: 0,
    is_active: true,
    remove_qr: false,
    qr: null,
});

function typeLabel(type) {
    const map = {
        jawwal_pay: 'payment.methodJawwal',
        bank_transfer: 'payment.methodBank',
        other_wallet: 'payment.methodOtherWallet',
        cash: 'payment.methodCash',
    };
    return t(map[type] || type);
}

function startEdit(m) {
    editId.value = m.id;
    editForm.type = m.type;
    editForm.label = m.label;
    editForm.account_holder = m.account_holder || '';
    editForm.account_identifier = m.account_identifier || '';
    editForm.note = m.note || '';
    editForm.sort_order = m.sort_order ?? 0;
    editForm.is_active = Boolean(m.is_active);
    editForm.remove_qr = false;
    editForm.qr = null;
}

function submitCreate() {
    createForm.post(route('admin.platform-payments.store'), {
        forceFormData: true,
        onSuccess: () => createForm.reset(),
    });
}

function submitEdit() {
    if (!editId.value) return;
    editForm.post(route('admin.platform-payments.update', editId.value), {
        forceFormData: true,
        onSuccess: () => {
            editId.value = null;
        },
    });
}

function destroyMethod(id) {
    if (!confirm(t('admin.deleteMethodConfirm'))) return;
    useForm({}).delete(route('admin.platform-payments.destroy', id));
}
</script>

<template>
    <AppLayout :title="t('admin.platformPaymentsTitle')">
        <Head :title="t('admin.platformPaymentsTitle')" />
        <PageHeader :title="t('admin.platformPaymentsTitle')" :subtitle="t('admin.platformPaymentsHint')" />

        <form class="wz-surface mb-4 max-w-xl space-y-3 p-5" @submit.prevent="settingsForm.post(route('admin.platform-payments.settings'))">
            <h2 class="font-semibold">{{ t('admin.commissionSetting') }}</h2>
            <Input
                id="commission_percent"
                v-model="settingsForm.commission_percent"
                type="number"
                step="0.01"
                :error="settingsForm.errors.commission_percent"
            >
                <template #label>{{ t('admin.commissionPercent') }}</template>
            </Input>
            <Button type="submit" :disabled="settingsForm.processing">{{ t('common.save') }}</Button>
        </form>

        <form class="wz-surface mb-6 max-w-2xl space-y-3 p-5" @submit.prevent="submitCreate">
            <h2 class="font-semibold">{{ t('admin.addPaymentMethod') }}</h2>
            <Select id="create-type" v-model="createForm.type" :error="createForm.errors.type">
                <template #label>{{ t('payment.method') }}</template>
                <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
            </Select>
            <Input id="create-label" v-model="createForm.label" :error="createForm.errors.label">
                <template #label>{{ t('admin.methodLabel') }}</template>
            </Input>
            <Input id="create-holder" v-model="createForm.account_holder" :error="createForm.errors.account_holder">
                <template #label>{{ t('admin.accountHolder') }}</template>
            </Input>
            <Input id="create-account" v-model="createForm.account_identifier" :error="createForm.errors.account_identifier">
                <template #label>{{ t('admin.accountIdentifier') }}</template>
            </Input>
            <label class="grid gap-1.5 text-sm">
                <span class="font-medium">{{ t('admin.qrImage') }}</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" @change="createForm.qr = $event.target.files?.[0] || null" />
            </label>
            <Button type="submit" variant="primary" :disabled="createForm.processing">{{ t('common.save') }}</Button>
        </form>

        <div class="wz-surface overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-wz-muted text-wz-fg-muted">
                    <tr>
                        <th class="px-3 py-2 text-start">{{ t('admin.methodLabel') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('payment.method') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('admin.accountIdentifier') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.status') }}</th>
                        <th class="px-3 py-2 text-start">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in methods" :key="m.id" class="border-t border-wz-border">
                        <td class="px-3 py-2">{{ m.label }}</td>
                        <td class="px-3 py-2">{{ typeLabel(m.type) }}</td>
                        <td class="px-3 py-2">{{ m.account_identifier || '—' }}</td>
                        <td class="px-3 py-2">
                            <Badge :tone="m.is_active ? 'success' : 'neutral'">
                                {{ m.is_active ? t('admin.active') : t('admin.suspended') }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" @click="startEdit(m)">{{ t('common.edit') }}</Button>
                                <Button size="sm" variant="danger" @click="destroyMethod(m.id)">{{ t('common.delete') }}</Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form
            v-if="editId"
            class="wz-surface mt-4 max-w-2xl space-y-3 p-5"
            @submit.prevent="submitEdit"
        >
            <h2 class="font-semibold">{{ t('common.edit') }} #{{ editId }}</h2>
            <Select id="edit-type" v-model="editForm.type">
                <template #label>{{ t('payment.method') }}</template>
                <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
            </Select>
            <Input id="edit-label" v-model="editForm.label">
                <template #label>{{ t('admin.methodLabel') }}</template>
            </Input>
            <Input id="edit-holder" v-model="editForm.account_holder">
                <template #label>{{ t('admin.accountHolder') }}</template>
            </Input>
            <Input id="edit-account" v-model="editForm.account_identifier">
                <template #label>{{ t('admin.accountIdentifier') }}</template>
            </Input>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="editForm.is_active" type="checkbox" />
                {{ t('admin.active') }}
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="editForm.remove_qr" type="checkbox" />
                {{ t('admin.removeQr') }}
            </label>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="editForm.qr = $event.target.files?.[0] || null" />
            <div class="flex gap-2">
                <Button type="submit" variant="primary">{{ t('common.save') }}</Button>
                <Button type="button" variant="ghost" @click="editId = null">{{ t('common.cancel') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
