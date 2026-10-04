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
    workspaces: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    workspace_id: props.workspaces[0]?.id ?? null,
    title: '',
    discount_percent: 10,
    starts_at: new Date().toISOString().slice(0, 10),
    ends_at: new Date().toISOString().slice(0, 10),
    is_active: true,
});

const dateOrderInvalid = computed(() => {
    if (!form.starts_at || !form.ends_at) return false;
    return form.ends_at < form.starts_at;
});

function submit() {
    if (dateOrderInvalid.value) return;
    form.post(route('owner.offers.store'));
}
</script>

<template>
    <AppLayout :title="t('owner.createOffer')">
        <Head :title="t('owner.createOffer')" />

        <PageHeader :title="t('owner.createOffer')" :subtitle="t('owner.offersSubtitle')">
            <template #actions>
                <Link :href="route('owner.offers.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-xl space-y-4 p-5" @submit.prevent="submit">
            <Select
                id="offer-workspace"
                :model-value="form.workspace_id ?? ''"
                :error="form.errors.workspace_id"
                :disabled="form.processing"
                @update:model-value="form.workspace_id = Number($event) || null"
            >
                <template #label>{{ t('bookings.workspace') }}</template>
                <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
            </Select>

            <Input
                id="offer-title"
                v-model="form.title"
                :error="form.errors.title"
                :disabled="form.processing"
            >
                <template #label>{{ t('owner.offerTitle') }}</template>
            </Input>

            <div class="grid gap-3 sm:grid-cols-2">
                <Input
                    id="offer-discount"
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
                    id="offer-starts"
                    v-model="form.starts_at"
                    type="date"
                    :error="form.errors.starts_at"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.startsAt') }}</template>
                </Input>
                <Input
                    id="offer-ends"
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
                <span v-if="String(form.errors.starts_at).toLowerCase().includes('overlap')" class="block">
                    {{ t('owner.overlapHint') }}
                </span>
            </p>

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(m, k) in form.errors" :key="k">
                    <template v-if="k !== 'starts_at' && k !== 'ends_at'">{{ m }}</template>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="form.processing || dateOrderInvalid || !form.workspace_id"
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
