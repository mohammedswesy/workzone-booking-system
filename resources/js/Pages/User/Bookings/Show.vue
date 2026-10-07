<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Input from '@/Components/Ui/Input.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { formatInDisplayTz } from '@/utils/datetime';

const props = defineProps({
    booking: { type: Object, required: true },
    platformPaymentMethods: { type: Array, default: () => [] },
});

const page = usePage();
const { t, locale } = useI18n();
const tz = computed(() => page.props.displayTimezone || 'Asia/Gaza');
const showCancel = ref(false);
const cancelling = ref(false);
const copiedKey = ref('');
const now = ref(Date.now());
let timer;

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});
onUnmounted(() => clearInterval(timer));

const methods = computed(() => props.platformPaymentMethods || []);

const manualForm = useForm({
    platform_payment_method_id: methods.value[0]?.id || null,
    transfer_reference: '',
    proof: null,
});

const fileName = ref('');

const selectedMethod = computed(() =>
    methods.value.find((m) => m.id === Number(manualForm.platform_payment_method_id)) || null,
);

const requiresReference = computed(() => Boolean(selectedMethod.value?.requires_reference));

const countdown = computed(() => {
    if (!props.booking.expires_at) return null;
    const ms = new Date(props.booking.expires_at).getTime() - now.value;
    if (ms <= 0) return { label: t('payment.expired'), urgent: true };
    const total = Math.floor(ms / 1000);
    const m = Math.floor(total / 60);
    const s = total % 60;
    return {
        label: `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`,
        urgent: total < 300,
    };
});

const canPay = computed(
    () =>
        props.booking.status === 'pending' &&
        ['unpaid', 'failed'].includes(props.booking.payment_status),
);

const waitingReview = computed(() => props.booking.payment_status === 'pending');

const latestRejected = computed(() => {
    const list = props.booking.payments || [];
    return list.find((p) => p.status === 'failed' && p.rejection_reason) || null;
});

function methodTypeLabel(type) {
    const map = {
        jawwal_pay: 'payment.methodJawwal',
        bank_transfer: 'payment.methodBank',
        other_wallet: 'payment.methodOtherWallet',
        cash: 'payment.methodCash',
        wallet: 'payment.methodOtherWallet',
    };
    return t(map[type] || type);
}

async function copyText(key, value) {
    if (!value) return;
    try {
        await navigator.clipboard.writeText(value);
        copiedKey.value = key;
        setTimeout(() => {
            copiedKey.value = '';
        }, 1500);
    } catch {
        /* ignore */
    }
}

function onProofChange(e) {
    const file = e.target.files?.[0] ?? null;
    manualForm.proof = file;
    fileName.value = file?.name || '';
}

function submitManual() {
    manualForm.post(route('user.payments.manual.store', props.booking.id), {
        forceFormData: true,
        onFinish: () => manualForm.reset('proof', 'transfer_reference'),
    });
}

function confirmCancel() {
    cancelling.value = true;
    router.delete(route('user.bookings.destroy', props.booking.id), {
        onFinish: () => {
            cancelling.value = false;
            showCancel.value = false;
        },
    });
}

function formatDate(value) {
    return formatInDisplayTz(value, locale.value, tz.value);
}
</script>

<template>
    <AppLayout :title="`${t('bookings.details')} #${booking.id}`">
        <Head :title="`${t('bookings.details')} #${booking.id}`" />

        <PageHeader
            :title="`${t('bookings.details')} #${booking.id}`"
            :subtitle="booking.workspace?.name || ''"
        >
            <template #actions>
                <Link :href="route('user.bookings.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="space-y-4 lg:col-span-2">
                <div class="wz-surface grid gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.workspace') }}</div>
                        <div class="mt-1 font-medium">{{ booking.workspace?.name }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.total') }}</div>
                        <div class="mt-1 font-display text-xl font-semibold">
                            $ {{ Number(booking.total_price ?? 0).toFixed(2) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.start') }}</div>
                        <div class="mt-1 font-medium">{{ formatDate(booking.start_at) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-wz-fg-muted">{{ t('bookings.end') }}</div>
                        <div class="mt-1 font-medium">{{ formatDate(booking.end_at) }}</div>
                    </div>
                </div>

                <div
                    v-if="countdown && booking.status === 'pending'"
                    class="rounded-xl border px-4 py-3 text-sm"
                    :class="countdown.urgent ? 'border-wz-danger/40 bg-red-50 text-wz-danger dark:bg-red-950/30' : 'border-wz-border bg-wz-muted text-wz-fg'"
                >
                    <span class="font-medium">{{ t('payment.expiresIn') }}:</span>
                    <span class="ms-2 font-mono text-lg">{{ countdown.label }}</span>
                </div>

                <div v-if="(canPay || waitingReview) && methods.length" class="space-y-3">
                    <h2 class="font-display text-lg font-semibold">{{ t('payment.payPlatformTitle') }}</h2>
                    <p class="text-sm text-wz-fg-muted">{{ t('payment.payPlatformHint') }}</p>

                    <article
                        v-for="(m, index) in methods"
                        :key="m.id"
                        class="wz-surface space-y-3 p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-wz-fg">
                                {{ index + 1 }}. {{ m.label }}
                                <span class="ms-2 text-xs font-normal text-wz-fg-muted">
                                    ({{ methodTypeLabel(m.type) }})
                                </span>
                            </h3>
                        </div>
                        <ol class="list-decimal space-y-2 ps-5 text-sm text-wz-fg">
                            <li v-if="m.account_holder">
                                {{ t('payment.stepPayTo') }}:
                                <strong>{{ m.account_holder }}</strong>
                                <Button size="sm" variant="ghost" class="ms-2" @click="copyText(`h-${m.id}`, m.account_holder)">
                                    {{ copiedKey === `h-${m.id}` ? t('admin.copied') : t('payment.copy') }}
                                </Button>
                            </li>
                            <li v-if="m.account_identifier">
                                {{ t('payment.stepAccount') }}:
                                <code class="rounded bg-wz-muted px-1.5 py-0.5">{{ m.account_identifier }}</code>
                                <Button size="sm" variant="ghost" class="ms-2" @click="copyText(`a-${m.id}`, m.account_identifier)">
                                    {{ copiedKey === `a-${m.id}` ? t('admin.copied') : t('payment.copy') }}
                                </Button>
                            </li>
                            <li v-if="m.qr_url">
                                {{ t('payment.stepScanQr') }}
                                <img :src="m.qr_url" alt="QR" class="mt-2 h-36 w-36 rounded-xl border border-wz-border object-contain bg-wz-elevated" />
                            </li>
                            <li v-if="m.note" class="text-wz-fg-muted">{{ m.note }}</li>
                        </ol>
                    </article>
                </div>

                <div
                    v-else-if="canPay || waitingReview"
                    class="rounded-xl border border-wz-warning/40 bg-wz-accent-soft p-4"
                >
                    <h2 class="font-semibold">{{ t('payment.detailsPendingTitle') }}</h2>
                    <p class="mt-1 text-sm text-wz-fg-muted">{{ t('payment.platformMethodsMissing') }}</p>
                </div>
            </section>

            <section class="wz-surface h-fit space-y-4 p-5">
                <h2 class="font-display text-lg font-semibold">{{ t('payment.title') }}</h2>
                <Badge :tone="booking.payment_status === 'paid' ? 'success' : 'warning'">
                    {{ t(`payment.${booking.payment_status}`, booking.payment_status) }}
                </Badge>

                <div
                    v-if="latestRejected && canPay"
                    class="rounded-xl border border-wz-danger/40 bg-red-50 px-3 py-2 text-sm text-wz-danger dark:bg-red-950/40"
                >
                    <p class="font-medium">{{ t('payment.rejectedNotice') }}</p>
                    <p class="mt-1 text-wz-fg">{{ latestRejected.rejection_reason }}</p>
                </div>

                <p v-if="waitingReview" class="rounded-xl bg-wz-accent-soft px-3 py-2 text-sm">
                    {{ t('payment.waitingAdminReview') }}
                </p>

                <p
                    v-else-if="canPay && !methods.length"
                    class="rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-3 py-2 text-sm text-wz-fg"
                >
                    {{ t('payment.platformMethodsMissing') }}
                </p>

                <form
                    v-else-if="canPay && methods.length"
                    class="space-y-3"
                    @submit.prevent="submitManual"
                >
                    <div class="grid gap-2">
                        <span class="text-sm font-medium">{{ t('payment.methodUsed') }}</span>
                        <label
                            v-for="m in methods"
                            :key="m.id"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm"
                        >
                            <input
                                v-model="manualForm.platform_payment_method_id"
                                type="radio"
                                :value="m.id"
                                :disabled="manualForm.processing"
                            />
                            {{ m.label }}
                        </label>
                        <p v-if="manualForm.errors.platform_payment_method_id" class="text-xs text-wz-danger">
                            {{ manualForm.errors.platform_payment_method_id }}
                        </p>
                    </div>

                    <Input
                        v-if="requiresReference"
                        id="transfer_reference"
                        v-model="manualForm.transfer_reference"
                        :error="manualForm.errors.transfer_reference"
                        :disabled="manualForm.processing"
                    >
                        <template #label>{{ t('payment.transferReference') }}</template>
                    </Input>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium">
                            {{ t('payment.uploadProof') }}
                            <span class="font-normal text-wz-fg-muted">({{ t('common.optional') }})</span>
                        </span>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="wz-focus block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-wz-brand-soft file:px-3 file:py-2 file:text-sm file:font-medium file:text-wz-brand"
                            :disabled="manualForm.processing"
                            @change="onProofChange"
                        />
                        <span class="text-xs text-wz-fg-muted">{{ fileName || t('payment.chooseFile') }}</span>
                        <p v-if="manualForm.errors.proof" class="text-xs text-wz-danger">{{ manualForm.errors.proof }}</p>
                    </label>

                    <Button type="submit" variant="primary" block :disabled="manualForm.processing">
                        {{ t('payment.submitProof') }}
                    </Button>
                </form>
            </section>
        </div>

        <ConfirmDialog
            :show="showCancel"
            :title="t('bookings.cancelTitle')"
            :message="t('bookings.cancelMessage')"
            :confirm-label="t('bookings.confirmCancel')"
            danger
            @confirm="confirmCancel"
            @cancel="showCancel = false"
        />
    </AppLayout>
</template>
