<script setup>
import { useI18n } from 'vue-i18n';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import GalleryManager from '@/Components/Ui/GalleryManager.vue';

const props = defineProps({
    form: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    owners: { type: Array, default: () => [] },
    showOwnerSelect: { type: Boolean, default: false },
    needsPaymentSetup: { type: Boolean, default: false },
    bookingModeLocked: { type: Boolean, default: false },
    workspace: { type: Object, default: null },
    idPrefix: { type: String, default: 'ws' },
});

const emit = defineEmits(['files']);

const { t } = useI18n();

const methodOptions = [
    { value: 'bank_transfer', labelKey: 'payment.methodBank' },
    { value: 'wallet', labelKey: 'payment.methodWallet' },
    { value: 'cash', labelKey: 'payment.methodCash' },
];

function toggleAmenity(id) {
    const key = Number(id);
    if (props.form.amenities.includes(key)) {
        props.form.amenities = props.form.amenities.filter((a) => a !== key);
    } else {
        props.form.amenities.push(key);
    }
}

function toggleMethod(value) {
    if (props.form.payment_methods.includes(value)) {
        props.form.payment_methods = props.form.payment_methods.filter((m) => m !== value);
    } else {
        props.form.payment_methods.push(value);
    }
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="needsPaymentSetup"
            class="rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm text-wz-fg"
            role="status"
        >
            <p class="font-semibold">{{ t('owner.paymentSetupBannerTitle') }}</p>
            <p class="mt-1 text-wz-fg-muted">{{ t('owner.paymentSetupFormHint') }}</p>
        </div>

        <Select
            v-if="showOwnerSelect"
            :id="`${idPrefix}-owner`"
            :model-value="form.owner_id ?? ''"
            :error="form.errors.owner_id"
            :disabled="form.processing"
            @update:model-value="form.owner_id = $event ? Number($event) : null"
        >
            <template #label>{{ t('admin.owner') }}</template>
            <option value="" disabled>{{ t('admin.selectOwner') }}</option>
            <option v-for="o in owners" :key="o.id" :value="o.id">
                {{ o.name }} — {{ o.email }}
            </option>
        </Select>

        <Input
            :id="`${idPrefix}-name`"
            v-model="form.name"
            :error="form.errors.name"
            :disabled="form.processing"
        >
            <template #label>{{ t('bookings.workspace') }}</template>
        </Input>

        <label class="grid gap-1.5">
            <span class="text-sm font-medium text-wz-fg">{{ t('owner.description') }}</span>
            <textarea
                v-model="form.description"
                rows="3"
                class="wz-focus w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:cursor-not-allowed disabled:opacity-55 disabled:text-wz-fg-muted"
                :disabled="form.processing"
            />
        </label>

        <div class="grid gap-3 md:grid-cols-2">
            <Input
                :id="`${idPrefix}-location`"
                v-model="form.location"
                :error="form.errors.location"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.locationText') }}</template>
            </Input>
            <Select
                :id="`${idPrefix}-location-id`"
                :model-value="form.location_id ?? ''"
                :disabled="form.processing"
                @update:model-value="form.location_id = $event ? Number($event) : null"
            >
                <template #label>{{ t('owner.locationPlace') }}</template>
                <option value="">{{ t('owner.selectLocation') }}</option>
                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                    {{ loc.name }}{{ loc.city ? ` — ${loc.city}` : '' }}
                </option>
            </Select>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <Input
                :id="`${idPrefix}-capacity`"
                v-model="form.capacity"
                type="number"
                :error="form.errors.capacity"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.capacity') }}</template>
            </Input>
            <Input
                :id="`${idPrefix}-price`"
                v-model="form.price_per_hour"
                type="number"
                :error="form.errors.price_per_hour"
                :disabled="form.processing"
            >
                <template #label>
                    {{
                        form.booking_mode === 'whole'
                            ? t('owner.pricePerHourWhole')
                            : t('owner.pricePerHourSeat')
                    }}
                </template>
            </Input>
        </div>

        <div class="rounded-xl border border-wz-border bg-wz-muted/40 p-4">
            <Select
                :id="`${idPrefix}-booking-mode`"
                v-model="form.booking_mode"
                :error="form.errors.booking_mode"
                :disabled="form.processing || bookingModeLocked"
            >
                <template #label>{{ t('owner.bookingMode') }}</template>
                <option value="seat">{{ t('owner.bookingModeSeat') }}</option>
                <option value="whole">{{ t('owner.bookingModeWhole') }}</option>
            </Select>
            <p class="mt-2 text-xs text-wz-fg-muted">
                {{
                    form.booking_mode === 'whole'
                        ? t('owner.bookingModeWholeHint')
                        : t('owner.bookingModeSeatHint')
                }}
            </p>
            <p v-if="bookingModeLocked" class="mt-2 text-xs text-wz-warning">
                {{ t('owner.bookingModeLocked') }}
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <Input
                :id="`${idPrefix}-open`"
                v-model="form.opening_time"
                type="time"
                :error="form.errors.opening_time"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.openingTime') }}</template>
            </Input>
            <Input
                :id="`${idPrefix}-close`"
                v-model="form.closing_time"
                type="time"
                :error="form.errors.closing_time"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.closingTime') }}</template>
            </Input>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <Select :id="`${idPrefix}-status`" v-model="form.status" :disabled="form.processing">
                <template #label>{{ t('owner.status') }}</template>
                <option value="draft">{{ t('owner.draft') }}</option>
                <option value="published">{{ t('owner.published') }}</option>
                <option value="archived">{{ t('owner.archived') }}</option>
            </Select>
            <label class="flex items-center gap-2 pt-7 text-sm text-wz-fg">
                <input
                    v-model="form.featured"
                    type="checkbox"
                    class="h-4 w-4 rounded border-wz-border text-wz-brand disabled:opacity-55"
                    :disabled="form.processing"
                />
                {{ t('owner.featured') }}
            </label>
        </div>

        <div class="space-y-3 rounded-xl border border-wz-border bg-wz-muted/40 p-4">
            <div>
                <p class="text-sm font-medium text-wz-fg">{{ t('owner.paymentInstructions') }}</p>
                <p class="mt-1 text-xs text-wz-fg-muted">{{ t('owner.paymentInstructionsHint') }}</p>
                <textarea
                    v-model="form.payment_instructions"
                    rows="4"
                    maxlength="2000"
                    class="wz-focus mt-2 w-full rounded-xl border border-wz-border bg-wz-elevated px-3 py-2.5 text-sm text-wz-fg disabled:cursor-not-allowed disabled:opacity-55 disabled:text-wz-fg-muted"
                    :disabled="form.processing"
                />
                <p v-if="form.errors.payment_instructions" class="mt-1 text-xs text-wz-danger">
                    {{ form.errors.payment_instructions }}
                </p>
            </div>
            <div>
                <p class="mb-2 text-sm font-medium text-wz-fg">{{ t('owner.paymentMethods') }}</p>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="m in methodOptions"
                        :key="m.value"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm text-wz-fg"
                    >
                        <input
                            type="checkbox"
                            class="h-4 w-4 rounded border-wz-border text-wz-brand disabled:opacity-55"
                            :checked="form.payment_methods.includes(m.value)"
                            :disabled="form.processing"
                            @change="toggleMethod(m.value)"
                        />
                        {{ t(m.labelKey) }}
                    </label>
                </div>
                <p v-if="form.errors.payment_methods" class="mt-1 text-xs text-wz-danger">
                    {{ form.errors.payment_methods }}
                </p>
            </div>
        </div>

        <div>
            <p class="mb-2 text-sm font-medium text-wz-fg">{{ t('owner.amenities') }}</p>
            <div class="flex flex-wrap gap-2">
                <label
                    v-for="a in amenities"
                    :key="a.id"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-wz-border bg-wz-elevated px-3 py-2 text-sm text-wz-fg"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-wz-border text-wz-brand disabled:opacity-55"
                        :checked="form.amenities.includes(a.id)"
                        :disabled="form.processing"
                        @change="toggleAmenity(a.id)"
                    />
                    {{ a.name }}
                </label>
            </div>
        </div>

        <GalleryManager
            :workspace-id="workspace?.id"
            :images="workspace?.images || []"
            @files="emit('files', $event)"
        />
    </div>
</template>
