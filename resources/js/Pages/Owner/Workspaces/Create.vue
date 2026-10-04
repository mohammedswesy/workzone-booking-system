<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import GalleryManager from '@/Components/Ui/GalleryManager.vue';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
});

const { t } = useI18n();

const form = useForm({
    name: '',
    description: '',
    location: '',
    location_id: null,
    capacity: 1,
    price_per_hour: 0,
    opening_time: '08:00',
    closing_time: '22:00',
    status: 'published',
    featured: false,
    payment_instructions: '',
    payment_methods: ['bank_transfer'],
    amenities: [],
    images: [],
});

const methodOptions = [
    { value: 'bank_transfer', labelKey: 'payment.methodBank' },
    { value: 'wallet', labelKey: 'payment.methodWallet' },
    { value: 'cash', labelKey: 'payment.methodCash' },
];

function onFiles(files) {
    form.images = files;
}

function toggleAmenity(id) {
    const key = Number(id);
    if (form.amenities.includes(key)) {
        form.amenities = form.amenities.filter((a) => a !== key);
    } else {
        form.amenities.push(key);
    }
}

function toggleMethod(value) {
    if (form.payment_methods.includes(value)) {
        form.payment_methods = form.payment_methods.filter((m) => m !== value);
    } else {
        form.payment_methods.push(value);
    }
}

function submit() {
    form.post(route('owner.workspaces.store'), { forceFormData: true });
}
</script>

<template>
    <AppLayout :title="t('owner.createWorkspace')">
        <Head :title="t('owner.createWorkspace')" />

        <PageHeader :title="t('owner.createWorkspace')" :subtitle="t('owner.workspacesSubtitle')">
            <template #actions>
                <Link :href="route('owner.workspaces.index')">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <form class="wz-surface mx-auto max-w-3xl space-y-5 p-5" @submit.prevent="submit">
            <Input id="ws-name" v-model="form.name" :error="form.errors.name" :disabled="form.processing">
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
                    id="ws-location"
                    v-model="form.location"
                    :error="form.errors.location"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.locationText') }}</template>
                </Input>
                <Select
                    id="ws-location-id"
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
                    id="ws-capacity"
                    v-model="form.capacity"
                    type="number"
                    :error="form.errors.capacity"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.capacity') }}</template>
                </Input>
                <Input
                    id="ws-price"
                    v-model="form.price_per_hour"
                    type="number"
                    :error="form.errors.price_per_hour"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.pricePerHour') }}</template>
                </Input>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <Input
                    id="ws-open"
                    v-model="form.opening_time"
                    type="time"
                    :error="form.errors.opening_time"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.openingTime') }}</template>
                </Input>
                <Input
                    id="ws-close"
                    v-model="form.closing_time"
                    type="time"
                    :error="form.errors.closing_time"
                    :disabled="form.processing"
                >
                    <template #label>{{ t('owner.closingTime') }}</template>
                </Input>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <Select
                    id="ws-status"
                    v-model="form.status"
                    :disabled="form.processing"
                >
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

            <GalleryManager @files="onFiles" />

            <div v-if="Object.keys(form.errors).length" class="space-y-1 text-sm text-wz-danger">
                <div v-for="(msg, key) in form.errors" :key="key">{{ msg }}</div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Button type="submit" variant="primary" :disabled="form.processing">
                    {{ t('common.save') }}
                </Button>
                <Link :href="route('owner.workspaces.index')">
                    <Button variant="ghost" :disabled="form.processing">{{ t('common.cancel') }}</Button>
                </Link>
            </div>
        </form>
    </AppLayout>
</template>
