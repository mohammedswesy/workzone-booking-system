<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    user: Object,
    isSelf: Boolean,
    isLastAdmin: Boolean,
    hasFinancialHistory: Boolean,
    mailDeliverable: Boolean,
});

const page = usePage();
const { t } = useI18n();
const showSuspend = ref(false);
const showDelete = ref(false);
const copied = ref(false);

const form = useForm({
    name: props.user.name,
    phone: props.user.phone || '',
    role: props.user.role ?? 'user',
});

function submit() {
    form.put(route('admin.users.update', props.user.id));
}

function suspend() {
    router.post(route('admin.users.suspend', props.user.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            showSuspend.value = false;
        },
    });
}

function reactivate() {
    router.post(route('admin.users.reactivate', props.user.id), {}, { preserveScroll: true });
}

function destroyUser() {
    router.delete(route('admin.users.destroy', props.user.id), {
        onFinish: () => {
            showDelete.value = false;
        },
    });
}

function resend() {
    router.post(route('admin.users.resend-invitation', props.user.id), {}, { preserveScroll: true });
}

async function copyLink() {
    const url = page.props.flash?.invitation?.setup_url;
    if (!url) return;
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        /* ignore */
    }
}
</script>

<template>
    <AppLayout :title="t('admin.editUser')">
        <Head :title="t('admin.editUser')" />

        <PageHeader :title="t('admin.editUser')" :subtitle="user.email">
            <template #actions>
                <Link :href="route('admin.users.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="page.props.flash?.invitation"
            class="mb-4 rounded-xl border border-wz-border bg-wz-brand-soft px-4 py-3 text-sm text-wz-fg"
        >
            <p v-if="page.props.flash.invitation.sent">
                {{ t('admin.inviteSent', { email: page.props.flash.invitation.email }) }}
            </p>
            <template v-else>
                <p class="font-medium">{{ t('admin.inviteLinkTitle') }}</p>
                <code class="mt-2 block break-all rounded-lg bg-wz-elevated px-3 py-2 text-xs">
                    {{ page.props.flash.invitation.setup_url }}
                </code>
                <Button class="mt-2" size="sm" variant="secondary" @click="copyLink">
                    {{ copied ? t('admin.copied') : t('admin.copyLink') }}
                </Button>
            </template>
        </div>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="user.is_active ? 'success' : 'danger'">
                    {{ user.is_active ? t('admin.active') : t('admin.suspended') }}
                </Badge>
                <Badge v-if="isSelf" tone="accent">{{ t('admin.cannotDeleteSelf') }}</Badge>
            </div>

            <Input id="edit-name" v-model="form.name" :error="form.errors.name" :disabled="form.processing">
                <template #label>{{ t('admin.name') }}</template>
            </Input>
            <Input id="edit-email" :model-value="user.email" disabled>
                <template #label>{{ t('admin.email') }}</template>
            </Input>
            <Input
                id="edit-phone"
                v-model="form.phone"
                :error="form.errors.phone"
                :disabled="form.processing"
            >
                <template #label>{{ t('admin.phone') }}</template>
            </Input>

            <Select
                id="edit-role"
                v-model="form.role"
                :error="form.errors.role"
                :disabled="form.processing || isSelf || isLastAdmin"
            >
                <template #label>{{ t('common.role') }}</template>
                <option value="user">{{ t('admin.roleUser') }}</option>
                <option value="owner">{{ t('admin.roleOwner') }}</option>
                <option value="admin">{{ t('admin.roleAdmin') }}</option>
            </Select>
            <p v-if="isLastAdmin && !isSelf" class="text-xs text-wz-fg-muted">
                {{ t('admin.cannotDeleteLastAdmin') }}
            </p>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Button type="button" variant="secondary" :disabled="form.processing" @click="resend">
                    {{ t('admin.resendInvite') }}
                </Button>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-wz-border pt-4">
                <Button
                    v-if="user.is_active && !isSelf"
                    type="button"
                    variant="danger"
                    :disabled="isLastAdmin"
                    @click="showSuspend = true"
                >
                    {{ t('admin.suspend') }}
                </Button>
                <Button
                    v-else-if="!user.is_active"
                    type="button"
                    variant="primary"
                    @click="reactivate"
                >
                    {{ t('admin.reactivate') }}
                </Button>
                <Button
                    v-if="!isSelf"
                    type="button"
                    variant="ghost"
                    :disabled="isLastAdmin || hasFinancialHistory"
                    @click="showDelete = true"
                >
                    {{ t('common.delete') }}
                </Button>
            </div>
            <p v-if="hasFinancialHistory" class="text-xs text-wz-fg-muted">
                {{ t('admin.cannotDeleteWithHistory') }}
            </p>
        </form>

        <ConfirmDialog
            :show="showSuspend"
            :title="t('admin.suspendTitle')"
            :message="t('admin.suspendMessage')"
            :confirm-label="t('admin.suspend')"
            danger
            @confirm="suspend"
            @cancel="showSuspend = false"
        />
        <ConfirmDialog
            :show="showDelete"
            :title="t('admin.deleteUserTitle')"
            :message="t('admin.deleteUserMessage')"
            :confirm-label="t('common.delete')"
            danger
            @confirm="destroyUser"
            @cancel="showDelete = false"
        />
    </AppLayout>
</template>
