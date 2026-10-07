<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
const page = usePage();
const user = page.props.auth.user;
const role = computed(() => String(page.props.auth?.role || '').toLowerCase());
const isOwner = computed(() => role.value === 'owner');

const form = useForm({
    name: user.name,
    email: user.email,
    payout_method: user.payout_method || 'bank_transfer',
    payout_account_holder: user.payout_account_holder || '',
    payout_account_identifier: user.payout_account_identifier || '',
    payout_note: user.payout_note || '',
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

            <div v-if="isOwner" class="space-y-3 border-t border-wz-border pt-4">
                <h3 class="font-semibold text-wz-fg">{{ t('profile.payoutTitle') }}</h3>
                <p class="text-sm text-wz-fg-muted">{{ t('profile.payoutHint') }}</p>
                <Select id="payout_method" v-model="form.payout_method" :error="form.errors.payout_method">
                    <template #label>{{ t('payment.method') }}</template>
                    <option value="jawwal_pay">{{ t('payment.methodJawwal') }}</option>
                    <option value="bank_transfer">{{ t('payment.methodBank') }}</option>
                    <option value="other_wallet">{{ t('payment.methodOtherWallet') }}</option>
                    <option value="cash">{{ t('payment.methodCash') }}</option>
                </Select>
                <Input
                    id="payout_account_holder"
                    v-model="form.payout_account_holder"
                    :error="form.errors.payout_account_holder"
                >
                    <template #label>{{ t('admin.accountHolder') }}</template>
                </Input>
                <Input
                    id="payout_account_identifier"
                    v-model="form.payout_account_identifier"
                    :error="form.errors.payout_account_identifier"
                >
                    <template #label>{{ t('admin.accountIdentifier') }}</template>
                </Input>
                <Input id="payout_note" v-model="form.payout_note" :error="form.errors.payout_note">
                    <template #label>{{ t('profile.payoutNote') }}</template>
                </Input>
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
