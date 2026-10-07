<script setup>
import { useI18n } from 'vue-i18n';
import Input from '@/Components/Ui/Input.vue';
import PasswordGeneratorFields from '@/Components/Admin/PasswordGeneratorFields.vue';

const props = defineProps({
    form: { type: Object, required: true },
    idPrefix: { type: String, default: 'owner' },
    mailDeliverable: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const { t } = useI18n();

function isDisabled() {
    return props.disabled || Boolean(props.form.processing);
}
</script>

<template>
    <div class="space-y-4">
        <Input
            :id="`${idPrefix}-name`"
            v-model="form.name"
            :error="form.errors.name"
            :disabled="isDisabled()"
        >
            <template #label>{{ t('admin.name') }}</template>
        </Input>
        <Input
            :id="`${idPrefix}-email`"
            v-model="form.email"
            type="email"
            autocomplete="off"
            :error="form.errors.email"
            :disabled="isDisabled()"
        >
            <template #label>{{ t('admin.email') }}</template>
        </Input>
        <Input
            :id="`${idPrefix}-phone`"
            v-model="form.phone"
            :error="form.errors.phone"
            :disabled="isDisabled()"
        >
            <template #label>{{ t('admin.phone') }}</template>
        </Input>

        <label class="flex items-start gap-3 rounded-xl border border-wz-border bg-wz-muted/40 px-3 py-3 text-sm text-wz-fg">
            <input
                v-model="form.send_invitation"
                type="checkbox"
                class="mt-1 rounded border-wz-border"
                :disabled="isDisabled()"
            />
            <span>
                <span class="font-medium">{{ t('admin.sendInvitationInstead') }}</span>
                <span class="mt-1 block text-xs text-wz-fg-muted">
                    {{ mailDeliverable ? t('admin.inviteWillEmail') : t('admin.inviteLinkHint') }}
                </span>
            </span>
        </label>

        <template v-if="!form.send_invitation">
            <PasswordGeneratorFields
                :password="form.password"
                :password-confirmation="form.password_confirmation"
                :password-error="form.errors.password"
                :confirmation-error="form.errors.password_confirmation"
                :disabled="isDisabled()"
                :id-prefix="idPrefix"
                @update:password="form.password = $event"
                @update:password-confirmation="form.password_confirmation = $event"
            />

            <label class="flex items-start gap-3 text-sm text-wz-fg">
                <input
                    v-model="form.must_change_password"
                    type="checkbox"
                    class="mt-1 rounded border-wz-border"
                    :disabled="isDisabled()"
                />
                <span>
                    <span class="font-medium">{{ t('admin.mustChangePassword') }}</span>
                    <span class="mt-1 block text-xs text-wz-fg-muted">{{ t('admin.mustChangePasswordHint') }}</span>
                </span>
            </label>
        </template>
    </div>
</template>
