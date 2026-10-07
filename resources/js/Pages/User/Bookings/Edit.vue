<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';
import { toDatetimeLocalInDisplayTz } from '@/utils/datetime';

const props = defineProps({
    booking: Object,
    workspaces: Array,
});

const page = usePage();
const { t } = useI18n();
const tz = page.props.displayTimezone || 'Asia/Gaza';

const form = useForm({
    workspace_id: props.booking.workspace_id,
    start_at: toDatetimeLocalInDisplayTz(props.booking.start_at, tz),
    end_at: toDatetimeLocalInDisplayTz(props.booking.end_at, tz),
});

function submit() {
    form.put(route('user.bookings.update', props.booking.id));
}
</script>

<template>
    <AppLayout :title="t('bookings.editTitle')">
        <Head :title="t('bookings.editTitle')" />

        <PageHeader
            :title="t('bookings.editTitle')"
            :subtitle="`#${booking.id}`"
        >
            <template #actions>
                <Link :href="route('user.bookings.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <Select
                id="edit_workspace_id"
                :model-value="form.workspace_id ?? ''"
                :error="form.errors.workspace_id"
                :disabled="form.processing"
                @update:model-value="form.workspace_id = Number($event) || null"
            >
                <template #label>{{ t('bookings.workspace') }}</template>
                <option value="" disabled>{{ t('bookings.selectWorkspace') }}</option>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">
                    {{ w.name }} (${{ Number(w.effective_price_per_hour ?? w.price_per_hour).toFixed(2) }}
                    {{
                        w.booking_mode === 'whole'
                            ? t('bookings.priceUnitWholeShort')
                            : t('bookings.priceUnitSeatShort')
                    }})
                </option>
            </Select>

            <Input
                id="edit_start_at"
                v-model="form.start_at"
                type="datetime-local"
                :error="form.errors.start_at"
                :disabled="form.processing"
            >
                <template #label>{{ t('bookings.start') }}</template>
            </Input>

            <Input
                id="edit_end_at"
                v-model="form.end_at"
                type="datetime-local"
                :error="form.errors.end_at"
                :disabled="form.processing"
            >
                <template #label>{{ t('bookings.end') }}</template>
            </Input>

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('user.bookings.index')">
                    <Button variant="ghost" :disabled="form.processing">
                        {{ t('common.cancel') }}
                    </Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
