<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
    password: { type: String, default: '' },
    passwordConfirmation: { type: String, default: '' },
    passwordError: { type: String, default: '' },
    confirmationError: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    idPrefix: { type: String, default: 'pw' },
});

const emit = defineEmits(['update:password', 'update:passwordConfirmation']);

const { t } = useI18n();
const showPassword = ref(false);
const copied = ref(false);

const inputType = computed(() => (showPassword.value ? 'text' : 'password'));

function generateStrongPassword(length = 16) {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnopqrstuvwxyz';
    const numbers = '23456789';
    const symbols = '!@#$%^&*_-+=';
    const all = upper + lower + numbers + symbols;
    const picks = [
        upper[randomIndex(upper.length)],
        lower[randomIndex(lower.length)],
        numbers[randomIndex(numbers.length)],
        symbols[randomIndex(symbols.length)],
    ];
    for (let i = picks.length; i < length; i += 1) {
        picks.push(all[randomIndex(all.length)]);
    }
    for (let i = picks.length - 1; i > 0; i -= 1) {
        const j = randomIndex(i + 1);
        [picks[i], picks[j]] = [picks[j], picks[i]];
    }
    return picks.join('');
}

function randomIndex(max) {
    const buf = new Uint32Array(1);
    crypto.getRandomValues(buf);
    return buf[0] % max;
}

function generate() {
    const value = generateStrongPassword(16);
    emit('update:password', value);
    emit('update:passwordConfirmation', value);
    showPassword.value = true;
}

async function copyPassword() {
    if (!props.password) return;
    try {
        await navigator.clipboard.writeText(props.password);
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
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="secondary" :disabled="disabled" @click="generate">
                {{ t('admin.generatePassword') }}
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                :disabled="disabled || !password"
                @click="showPassword = !showPassword"
            >
                {{ showPassword ? t('admin.hidePassword') : t('admin.showPassword') }}
            </Button>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                :disabled="disabled || !password"
                @click="copyPassword"
            >
                {{ copied ? t('admin.copied') : t('admin.copyPassword') }}
            </Button>
        </div>
        <p class="text-xs text-wz-fg-muted">{{ t('admin.passwordPlainHint') }}</p>

        <Input
            :id="`${idPrefix}-password`"
            :model-value="password"
            :type="inputType"
            autocomplete="new-password"
            :error="passwordError"
            :disabled="disabled"
            @update:model-value="emit('update:password', $event)"
        >
            <template #label>{{ t('admin.password') }}</template>
        </Input>
        <Input
            :id="`${idPrefix}-password-confirmation`"
            :model-value="passwordConfirmation"
            :type="inputType"
            autocomplete="new-password"
            :error="confirmationError"
            :disabled="disabled"
            @update:model-value="emit('update:passwordConfirmation', $event)"
        >
            <template #label>{{ t('admin.passwordConfirmation') }}</template>
        </Input>
    </div>
</template>
