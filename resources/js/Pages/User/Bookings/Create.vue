<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';
import { nowDatetimeLocalInDisplayTz } from '@/utils/datetime';

const page = usePage();
const { t } = useI18n();
const tz = computed(() => page.props.displayTimezone || 'Asia/Gaza');

const workspace = page.props?.workspace ?? null;
const workspaces = page.props?.workspaces ?? [];

const form = useForm({
    workspace_id: workspace?.id ?? null,
    start_at: nowDatetimeLocalInDisplayTz(1, tz.value),
    end_at: nowDatetimeLocalInDisplayTz(2, tz.value),
    seats: 1,
});

const remainingSeats = ref(null);
const quoteTotal = ref(null);
const previewLoading = ref(false);
const available = ref(true);
const reason = ref('');
const nextSlot = ref(null);
const schedule = ref([]);

const selected = computed(() => {
    if (workspace && workspace.id === form.workspace_id) return workspace;
    return workspaces.find((w) => w.id === form.workspace_id) ?? workspace;
});

const isSeatMode = computed(() => (selected.value?.booking_mode || 'seat') === 'seat');

const hours = computed(() => {
    if (!form.start_at || !form.end_at) return 0;
    const ms = new Date(form.end_at) - new Date(form.start_at);
    return ms > 0 ? ms / 3600000 : 0;
});

const maxSeats = computed(() => {
    if (!isSeatMode.value) return selected.value?.capacity || 1;
    if (remainingSeats.value === null) return selected.value?.capacity || 1;
    return Math.max(1, remainingSeats.value);
});

const priceUnitLabel = computed(() =>
    isSeatMode.value ? t('bookings.priceUnitSeat') : t('bookings.priceUnitWhole'),
);

const displayTotal = computed(() => {
    if (quoteTotal.value !== null) return Number(quoteTotal.value).toFixed(2);
    const rate = Number(selected.value?.effective_price_per_hour ?? selected.value?.price_per_hour ?? 0);
    const billableSeats = isSeatMode.value ? Number(form.seats || 1) : 1;
    return (rate * hours.value * billableSeats).toFixed(2);
});

const dayNames = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

async function refreshAvailability() {
    if (!form.workspace_id || !form.start_at || !form.end_at) return;
    if (new Date(form.end_at) <= new Date(form.start_at)) return;

    previewLoading.value = true;
    try {
        const { data } = await axios.get(route('user.bookings.availability'), {
            params: {
                workspace_id: form.workspace_id,
                start_at: form.start_at,
                end_at: form.end_at,
                seats: form.seats,
            },
        });
        remainingSeats.value = data.remaining_seats;
        quoteTotal.value = data.quote?.final_amount ?? null;
        available.value = Boolean(data.available);
        reason.value = data.reason || '';
        nextSlot.value = data.next_slot || null;
        schedule.value = data.schedule || selected.value?.schedule || [];
        if (data.booking_mode === 'seat' && form.seats > data.remaining_seats && data.remaining_seats > 0) {
            form.seats = data.remaining_seats;
        }
        if (data.booking_mode === 'whole') {
            form.seats = data.capacity;
        }
    } catch {
        remainingSeats.value = null;
        quoteTotal.value = null;
        available.value = true;
        reason.value = '';
        nextSlot.value = null;
    } finally {
        previewLoading.value = false;
    }
}

function applyNextSlot() {
    if (!nextSlot.value?.local_start || !nextSlot.value?.local_end) return;
    form.start_at = nextSlot.value.local_start.replace(' ', 'T').slice(0, 16);
    form.end_at = nextSlot.value.local_end.replace(' ', 'T').slice(0, 16);
}

watch(
    () => [form.workspace_id, form.start_at, form.end_at, form.seats],
    () => {
        refreshAvailability();
    },
    { immediate: true },
);

function submit() {
    form.post(route('user.bookings.store'), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('bookings.createTitle')">
        <Head :title="t('bookings.createTitle')" />

        <PageHeader :title="t('bookings.createTitle')" :subtitle="t('bookings.subtitle')">
            <template #actions>
                <Link :href="route('spaces.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-2xl space-y-4 p-5" @submit.prevent="submit">
            <Select
                id="workspace_id"
                :model-value="form.workspace_id ?? ''"
                :error="form.errors.workspace_id"
                :disabled="form.processing"
                @update:model-value="form.workspace_id = Number($event) || null"
            >
                <template #label>{{ t('bookings.workspace') }}</template>
                <option value="" disabled>{{ t('bookings.selectWorkspace') }}</option>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">
                    {{ w.name }} — ${{ Number(w.effective_price_per_hour ?? w.price_per_hour).toFixed(2) }}
                    {{
                        w.booking_mode === 'whole'
                            ? t('bookings.priceUnitWholeShort')
                            : t('bookings.priceUnitSeatShort')
                    }}
                    · {{ t('spaces.capacity', { n: w.capacity }) }}
                </option>
            </Select>

            <div class="grid gap-3 md:grid-cols-2">
                <Input
                    id="start_at"
                    v-model="form.start_at"
                    type="datetime-local"
                    :error="form.errors.start_at"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('bookings.start') }}</template>
                </Input>
                <Input
                    id="end_at"
                    v-model="form.end_at"
                    type="datetime-local"
                    :error="form.errors.end_at"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('bookings.end') }}</template>
                </Input>
            </div>

            <div v-if="selected && isSeatMode" class="space-y-2">
                <Input
                    id="seats"
                    v-model="form.seats"
                    type="number"
                    :error="form.errors.seats"
                    :disabled="form.processing || maxSeats < 1"
                >
                    <template #label>{{ t('bookings.seats') }}</template>
                </Input>
                <p class="text-sm text-wz-fg-muted">
                    {{ t('bookings.remainingSeats', { n: remainingSeats ?? selected.capacity }) }}
                    <span v-if="previewLoading"> · {{ t('common.loading') }}</span>
                </p>
            </div>
            <p v-else-if="selected" class="text-sm text-wz-fg-muted">
                {{ t('bookings.wholeSpaceNotice', { n: selected.capacity }) }}
            </p>

            <ul v-if="(schedule.length || selected?.schedule?.length)" class="text-xs text-wz-fg-muted">
                <li
                    v-for="day in (schedule.length ? schedule : selected.schedule)"
                    :key="day.weekday"
                >
                    {{ t(`availability.days.${dayNames[day.weekday]}`) }}:
                    <template v-if="day.is_closed">{{ t('availability.closed') }}</template>
                    <template v-else>{{ day.opens_at }}–{{ day.closes_at }}</template>
                </li>
            </ul>

            <div
                v-if="!available && reason"
                class="rounded-xl border border-wz-danger/30 bg-wz-danger/5 px-4 py-3 text-sm text-wz-danger"
            >
                <p>{{ reason }}</p>
                <Button
                    v-if="nextSlot"
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="mt-2"
                    @click="applyNextSlot"
                >
                    {{ t('availability.useNextSlot') }}
                    <span v-if="nextSlot.local_start" class="ms-1 opacity-80">
                        ({{ nextSlot.local_start }})
                    </span>
                </Button>
            </div>

            <p v-if="selected?.active_discount_percent" class="text-sm text-wz-fg-muted">
                {{ t('bookings.offerOff', { n: selected.active_discount_percent }) }}
            </p>

            <div class="rounded-xl bg-wz-muted px-4 py-3 text-sm text-wz-fg">
                <p class="text-xs text-wz-fg-muted">{{ priceUnitLabel }}</p>
                <p class="mt-1">
                    {{ t('bookings.duration') }}:
                    <span class="font-medium">{{ hours.toFixed(2) }}h</span>
                    <span v-if="isSeatMode" class="mx-2 text-wz-fg-muted">·</span>
                    <template v-if="isSeatMode">
                        {{ t('bookings.seats') }}:
                        <span class="font-medium">{{ form.seats }}</span>
                    </template>
                    <span class="mx-2 text-wz-fg-muted">·</span>
                    {{ t('bookings.estimatedTotal') }}:
                    <span class="font-semibold">$ {{ displayTotal }}</span>
                </p>
            </div>

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="form.processing || !form.workspace_id || !available || (isSeatMode && maxSeats < 1)"
                >
                    {{ t('bookings.confirmCreate') }}
                </Button>
                <Link :href="route('spaces.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
