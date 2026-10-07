<script setup>
import { useI18n } from 'vue-i18n';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import GalleryManager from '@/Components/Ui/GalleryManager.vue';

const props = defineProps({
    form: { type: Object, required: true },
    amenities: { type: Array, default: () => [] },
    bookingModeLocked: { type: Boolean, default: false },
    workspace: { type: Object, default: null },
    idPrefix: { type: String, default: 'unit' },
});

const emit = defineEmits(['files']);
const { t } = useI18n();

function toggleAmenity(id) {
    const key = Number(id);
    if (props.form.amenities.includes(key)) {
        props.form.amenities = props.form.amenities.filter((a) => a !== key);
    } else {
        props.form.amenities.push(key);
    }
}
</script>

<template>
    <div class="space-y-5">
        <Input
            :id="`${idPrefix}-name`"
            v-model="form.name"
            :error="form.errors?.name"
            :disabled="form.processing"
        >
            <template #label>{{ t('venues.unitName') }}</template>
        </Input>

        <div class="grid gap-4 sm:grid-cols-2">
            <Select
                :id="`${idPrefix}-type`"
                v-model="form.type"
                :error="form.errors?.type"
                :disabled="form.processing"
            >
                <template #label>{{ t('venues.unitType') }}</template>
                <option value="hot_desk">{{ t('venues.types.hot_desk') }}</option>
                <option value="private_office">{{ t('venues.types.private_office') }}</option>
                <option value="meeting_room">{{ t('venues.types.meeting_room') }}</option>
                <option value="training_room">{{ t('venues.types.training_room') }}</option>
                <option value="other">{{ t('venues.types.other') }}</option>
            </Select>

            <Select
                :id="`${idPrefix}-mode`"
                v-model="form.booking_mode"
                :error="form.errors?.booking_mode"
                :disabled="form.processing || bookingModeLocked"
            >
                <template #label>{{ t('owner.bookingMode') }}</template>
                <option value="seat">{{ t('owner.bookingModeSeat') }}</option>
                <option value="whole">{{ t('owner.bookingModeWhole') }}</option>
            </Select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Input
                :id="`${idPrefix}-capacity`"
                v-model="form.capacity"
                type="number"
                min="1"
                :error="form.errors?.capacity"
                :disabled="form.processing"
            >
                <template #label>{{ t('venues.capacity') }}</template>
            </Input>
            <Input
                :id="`${idPrefix}-price`"
                v-model="form.price_per_hour"
                type="number"
                min="0"
                step="0.01"
                :error="form.errors?.price_per_hour"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.pricePerHour') }}</template>
            </Input>
        </div>

        <p class="text-xs text-wz-fg-muted">{{ t('venues.manageHours') }} — use the venue availability page.</p>

        <Select
            :id="`${idPrefix}-status`"
            v-model="form.status"
            :error="form.errors?.status"
            :disabled="form.processing"
        >
            <template #label>{{ t('common.status') }}</template>
            <option value="draft">{{ t('venues.statusDraft') }}</option>
            <option value="published">{{ t('venues.statusPublished') }}</option>
            <option value="archived">{{ t('venues.statusArchived') }}</option>
        </Select>

        <div>
            <p class="mb-2 text-sm font-medium">{{ t('owner.amenities') }}</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="a in amenities"
                    :key="a.id"
                    type="button"
                    class="rounded-lg border px-2 py-1 text-xs"
                    :class="form.amenities.includes(a.id) ? 'border-wz-brand bg-wz-brand-soft text-wz-brand' : 'border-wz-border'"
                    @click="toggleAmenity(a.id)"
                >
                    {{ a.name }}
                </button>
            </div>
        </div>

        <GalleryManager
            :workspace-id="workspace?.id || null"
            :images="workspace?.images || []"
            @files="emit('files', $event)"
        />
    </div>
</template>
