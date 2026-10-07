<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    offer: { type: Object, required: true },
    workspaces: { type: Array, default: () => [] },
    venues: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    scope: props.offer.venue_id && !props.offer.workspace_id ? 'venue' : 'unit',
    workspace_id: props.offer.workspace_id,
    venue_id: props.offer.venue_id,
    title: props.offer.title,
    discount_percent: props.offer.discount_percent,
    starts_at: props.offer.starts_at?.slice?.(0, 10) || props.offer.starts_at,
    ends_at: props.offer.ends_at?.slice?.(0, 10) || props.offer.ends_at,
    is_active: !!props.offer.is_active,
});

const dateOrderInvalid = computed(() => {
    if (!form.starts_at || !form.ends_at) return false;
    return form.ends_at < form.starts_at;
});

const canSubmit = computed(() => {
    if (form.scope === 'unit') return Boolean(form.workspace_id);
    return Boolean(form.venue_id);
});

function submit() {
    if (dateOrderInvalid.value || !canSubmit.value) return;
    form.put(route('owner.offers.update', props.offer.id));
}
</script>

<template>
    <AppLayout :title="t('owner.editOffer')">
        <Head :title="t('owner.editOffer')" />

        <PageHeader :title="t('owner.editOffer')" :subtitle="offer.title">
            <template #actions>
                <Link :href="route('owner.offers.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <Select
                id="edit-offer-scope"
                v-model="form.scope"
                :error="form.errors.scope"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.offerScope') }}</template>
                <option value="unit">{{ t('owner.offerScopeUnit') }}</option>
                <option value="venue">{{ t('owner.offerScopeVenue') }}</option>
            </Select>

            <Select
                v-if="form.scope === 'unit'"
                id="edit-offer-workspace"
                :model-value="form.workspace_id ?? ''"
                :error="form.errors.workspace_id"
                :disabled="form.processing"
                @update:model-value="form.workspace_id = Number($event) || null"
            >
                <template #label>{{ t('owner.offerUnit') }}</template>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
            </Select>

            <Select
                v-else
                id="edit-offer-venue"
                :model-value="form.venue_id ?? ''"
                :error="form.errors.venue_id"
                :disabled="form.processing"
                @update:model-value="form.venue_id = Number($event) || null"
            >
                <template #label>{{ t('owner.offerVenue') }}</template>
                <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
            </Select>

            <Input
                id="edit-offer-title"
                v-model="form.title"
                :error="form.errors.title"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.offerTitle') }}</template>
            </Input>

            <div class="grid gap-3 sm:grid-cols-2">
                <Input
                    id="edit-offer-discount"
                    v-model="form.discount_percent"
                    type="number"
                    :error="form.errors.discount_percent"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.discount') }}</template>
                </Input>
                <label class="flex items-center gap-2 pt-7 text-sm text-wz-fg">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-wz-border text-wz-brand disabled:opacity-55"
                        :disabled="form.processing"
                    />
                    {{ t('owner.isActive') }}
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <Input
                    id="edit-offer-starts"
                    v-model="form.starts_at"
                    type="date"
                    :error="form.errors.starts_at"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.startsAt') }}</template>
                </Input>
                <Input
                    id="edit-offer-ends"
                    v-model="form.ends_at"
                    type="date"
                    :error="form.errors.ends_at"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.endsAt') }}</template>
                </Input>
            </div>

            <p v-if="dateOrderInvalid" class="text-sm text-wz-danger">
                {{ t('owner.dateOrderHint') }}
            </p>
            <p v-else-if="form.errors.starts_at" class="text-sm text-wz-danger">
                {{ form.errors.starts_at }}
            </p>

            <div class="flex flex-wrap gap-3">
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="form.processing || dateOrderInvalid || !canSubmit"
                >
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('owner.offers.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
