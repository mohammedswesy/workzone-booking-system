<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    email: { type: String, required: true },
    plainPassword: { type: String, default: null },
    invitation: { type: Object, default: null },
});

const { t } = useI18n();
const copiedEmail = ref(false);
const copiedPassword = ref(false);
const copiedLink = ref(false);

async function copy(text, flag) {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        flag.value = true;
        setTimeout(() => {
            flag.value = false;
        }, 2000);
    } catch {
        /* ignore */
    }
}
</script>

<template>
    <div class="space-y-4 rounded-xl border border-wz-border bg-wz-brand-soft px-4 py-4 text-sm text-wz-fg">
        <p class="font-medium">{{ t('admin.ownerCreatedTitle') }}</p>

        <div>
            <p class="text-xs font-medium text-wz-fg-muted">{{ t('admin.email') }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <code class="break-all rounded-lg bg-wz-elevated px-3 py-2 text-xs">{{ email }}</code>
                <Button type="button" size="sm" variant="secondary" @click="copy(email, copiedEmail)">
                    {{ copiedEmail ? t('admin.copied') : t('admin.copyEmail') }}
                </Button>
            </div>
        </div>

        <template v-if="plainPassword">
            <div>
                <p class="text-xs font-medium text-wz-fg-muted">{{ t('admin.password') }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <code class="break-all rounded-lg bg-wz-elevated px-3 py-2 text-xs">{{ plainPassword }}</code>
                    <Button type="button" size="sm" variant="secondary" @click="copy(plainPassword, copiedPassword)">
                        {{ copiedPassword ? t('admin.copied') : t('admin.copyPassword') }}
                    </Button>
                </div>
            </div>
            <p class="rounded-lg border border-wz-danger/30 bg-wz-elevated px-3 py-2 text-xs text-wz-danger">
                {{ t('admin.passwordShownOnce') }}
            </p>
        </template>

        <template v-else-if="invitation">
            <p v-if="invitation.sent">
                {{ t('admin.inviteSent', { email: invitation.email || email }) }}
            </p>
            <template v-else>
                <p class="font-medium">{{ t('admin.inviteLinkTitle') }}</p>
                <p class="mt-1 text-wz-fg-muted">{{ t('admin.inviteLinkHint') }}</p>
                <code class="mt-2 block break-all rounded-lg bg-wz-elevated px-3 py-2 text-xs">
                    {{ invitation.setup_url }}
                </code>
                <Button
                    class="mt-2"
                    type="button"
                    size="sm"
                    variant="secondary"
                    @click="copy(invitation.setup_url, copiedLink)"
                >
                    {{ copiedLink ? t('admin.copied') : t('admin.copyLink') }}
                </Button>
            </template>
        </template>
    </div>
</template>
