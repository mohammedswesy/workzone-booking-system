<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';

const page = usePage();
const { t } = useI18n();

const workspace = page.props?.workspace ?? null;
const workspaces = page.props?.workspaces ?? [];

function defaultStart() {
    const d = new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(d.getHours() + 1);
    return toLocalInput(d);
}

function defaultEnd() {
    const d = new Date();
    d.setMinutes(0, 0, 0);
    d.setHours(d.getHours() + 2);
    return toLocalInput(d);
}

function toLocalInput(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const form = useForm({
    workspace_id: workspace?.id ?? null,
    start_at: defaultStart(),
    end_at: defaultEnd(),
});

const selected = computed(() => {
    if (workspace && workspace.id === form.workspace_id) return workspace;
    return workspaces.find((w) => w.id === form.workspace_id) ?? workspace;
});

const hours = computed(() => {
    if (!form.start_at || !form.end_at) return 0;
    const ms = new Date(form.end_at) - new Date(form.start_at);
    return ms > 0 ? ms / 3600000 : 0;
});

const total = computed(() => {
    const rate = Number(selected.value?.effective_price_per_hour ?? selected.value?.price_per_hour ?? 0);
    return (rate * hours.value).toFixed(2);
});

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
                    {{ w.name }} — ${{ Number(w.effective_price_per_hour ?? w.price_per_hour).toFixed(2) }}/h
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

            <p v-if="selected" class="text-sm text-wz-fg-muted">
                {{
                    t('bookings.openingHours', {
                        open: selected.opening_time,
                        close: selected.closing_time,
                    })
                }}
                <span v-if="selected.active_discount_percent">
                    · {{ t('bookings.offerOff', { n: selected.active_discount_percent }) }}
                </span>
            </p>

            <div class="rounded-xl bg-wz-muted px-4 py-3 text-sm text-wz-fg">
                {{ t('bookings.duration') }}:
                <span class="font-medium">{{ hours.toFixed(2) }}h</span>
                <span class="mx-2 text-wz-fg-muted">·</span>
                {{ t('bookings.estimatedTotal') }}:
                <span class="font-semibold">$ {{ total }}</span>
            </div>

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <Button type="submit" variant="primary" :disabled="form.processing || !form.workspace_id">
                    {{ t('bookings.confirmCreate') }}
                </Button>
                <Link :href="route('spaces.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
