<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    venue: { type: Object, required: true },
    schedule: { type: Array, required: true },
    timezone: { type: String, required: true },
    units: { type: Array, default: () => [] },
    exceptions: { type: Array, default: () => [] },
    pause: { type: Object, required: true },
    hours_config_needed: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const isAdmin = computed(() => String(page.url || '').startsWith('/admin'));
const prefix = computed(() => (isAdmin.value ? 'admin' : 'owner'));

const dayNames = computed(() => [
    t('availability.days.sun'),
    t('availability.days.mon'),
    t('availability.days.tue'),
    t('availability.days.wed'),
    t('availability.days.thu'),
    t('availability.days.fri'),
    t('availability.days.sat'),
]);

const hoursForm = useForm({
    timezone: props.timezone,
    days: props.schedule.map((d) => ({
        weekday: d.weekday,
        is_closed: Boolean(d.is_closed),
        opens_at: d.opens_at || '08:00',
        closes_at: d.closes_at || '22:00',
    })),
});

const pauseForm = useForm({
    bookings_paused: Boolean(props.pause.bookings_paused),
    bookings_paused_note: props.pause.bookings_paused_note || '',
    acknowledge_conflicts: false,
    workspace_id: null,
});

const selectedUnitId = ref(props.units[0]?.id ?? null);
const selectedUnit = computed(() => props.units.find((u) => u.id === selectedUnitId.value) || null);

const unitForm = useForm({
    inherits_venue_hours: true,
    days: [],
});

function loadUnit() {
    const u = selectedUnit.value;
    if (!u) return;
    unitForm.inherits_venue_hours = Boolean(u.inherits_venue_hours);
    unitForm.days = (u.schedule || []).map((d) => ({
        weekday: d.weekday,
        is_closed: Boolean(d.is_closed),
        opens_at: d.opens_at || '08:00',
        closes_at: d.closes_at || '22:00',
    }));
}
loadUnit();

const exceptionForm = useForm({
    scope: 'venue',
    workspace_id: null,
    starts_on: '',
    ends_on: '',
    type: 'closed',
    reason: '',
    opens_at: '09:00',
    closes_at: '17:00',
    acknowledge_conflicts: false,
});

const conflicts = computed(() => page.props.flash?.availability_conflicts || []);

function copyFirstToAll() {
    const first = hoursForm.days[0];
    hoursForm.days = hoursForm.days.map((d) => ({
        ...d,
        is_closed: first.is_closed,
        opens_at: first.opens_at,
        closes_at: first.closes_at,
    }));
}

function saveHours() {
    hoursForm.put(route(`${prefix.value}.venues.availability.hours`, props.venue.slug), { preserveScroll: true });
}

function saveUnitHours() {
    if (!selectedUnit.value) return;
    unitForm.put(
        route(`${prefix.value}.venues.units.availability.hours`, [props.venue.slug, selectedUnit.value.id]),
        { preserveScroll: true },
    );
}

function savePause() {
    pauseForm.put(route(`${prefix.value}.venues.availability.pause`, props.venue.slug), { preserveScroll: true });
}

function saveException() {
    exceptionForm.post(route(`${prefix.value}.venues.availability.exceptions.store`, props.venue.slug), {
        preserveScroll: true,
        onSuccess: () => {
            exceptionForm.reset('reason');
            exceptionForm.acknowledge_conflicts = false;
        },
    });
}

function removeException(ex) {
    if (!confirm(t('common.delete') + '?')) return;
    router.delete(route(`${prefix.value}.venues.availability.exceptions.destroy`, [props.venue.slug, ex.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="t('availability.title')">
        <Head :title="t('availability.title')" />
        <PageHeader :title="t('availability.title')" :subtitle="venue.name">
            <template #actions>
                <Link :href="route(`${prefix}.venues.show`, venue.slug)">
                    <Button variant="secondary">{{ t('common.back') }}</Button>
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="hours_config_needed"
            class="mb-4 rounded-xl border border-wz-warning/40 bg-wz-accent-soft px-4 py-3 text-sm"
            role="alert"
            data-testid="hours-config-banner"
        >
            {{ t('availability.hoursNotConfigured') }}
        </div>

        <div v-if="conflicts.length" class="mb-4 rounded-xl border border-wz-danger/40 bg-wz-danger/10 p-4 text-sm" data-testid="availability-conflicts">
            <p class="font-medium text-wz-danger">{{ t('availability.conflictsTitle') }}</p>
            <ul class="mt-2 space-y-1">
                <li v-for="c in conflicts" :key="c.id">
                    #{{ c.id }} — {{ c.user }} · {{ c.unit }} · {{ c.start_at }} → {{ c.end_at }}
                </li>
            </ul>
            <p class="mt-2 text-wz-fg-muted">{{ t('availability.conflictsHint') }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <form class="wz-surface space-y-4 p-5" @submit.prevent="saveHours">
                <h2 class="font-semibold">{{ t('availability.weeklyHours') }}</h2>
                <Input v-model="hoursForm.timezone" :error="hoursForm.errors.timezone">
                    <template #label>{{ t('availability.timezone') }}</template>
                </Input>
                <div class="space-y-2">
                    <div
                        v-for="(day, idx) in hoursForm.days"
                        :key="day.weekday"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 text-sm"
                    >
                        <span class="font-medium">{{ dayNames[day.weekday] }}</span>
                        <label class="flex items-center gap-1">
                            <input v-model="day.is_closed" type="checkbox" class="rounded border-wz-border" />
                            {{ t('availability.closed') }}
                        </label>
                        <input
                            v-model="day.opens_at"
                            type="time"
                            class="wz-focus rounded-lg border border-wz-border bg-wz-elevated px-2 py-1"
                            :disabled="day.is_closed"
                        />
                        <input
                            v-model="day.closes_at"
                            type="time"
                            class="wz-focus rounded-lg border border-wz-border bg-wz-elevated px-2 py-1"
                            :disabled="day.is_closed"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" @click="copyFirstToAll">{{ t('availability.copyAll') }}</Button>
                    <Button type="submit" :disabled="hoursForm.processing">{{ t('common.save') }}</Button>
                </div>
            </form>

            <form class="wz-surface space-y-4 p-5" @submit.prevent="savePause">
                <h2 class="font-semibold">{{ t('availability.pauseTitle') }}</h2>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="pauseForm.bookings_paused" type="checkbox" class="rounded border-wz-border" />
                    {{ t('availability.pauseToggle') }}
                </label>
                <Input v-model="pauseForm.bookings_paused_note" :error="pauseForm.errors.bookings_paused_note">
                    <template #label>{{ t('availability.pauseNote') }}</template>
                </Input>
                <label v-if="conflicts.length" class="flex items-center gap-2 text-sm">
                    <input v-model="pauseForm.acknowledge_conflicts" type="checkbox" class="rounded border-wz-border" />
                    {{ t('availability.acknowledge') }}
                </label>
                <p v-if="pauseForm.errors.acknowledge_conflicts" class="text-sm text-wz-danger">
                    {{ pauseForm.errors.acknowledge_conflicts }}
                </p>
                <Button type="submit" :disabled="pauseForm.processing">{{ t('common.save') }}</Button>
            </form>

            <form class="wz-surface space-y-4 p-5" @submit.prevent="saveUnitHours">
                <h2 class="font-semibold">{{ t('availability.unitHours') }}</h2>
                <Select
                    :model-value="selectedUnitId ?? ''"
                    @update:model-value="selectedUnitId = Number($event); loadUnit()"
                >
                    <template #label>{{ t('owner.offerUnit') }}</template>
                    <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                </Select>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="unitForm.inherits_venue_hours" type="checkbox" class="rounded border-wz-border" />
                    {{ t('availability.inheritVenue') }}
                </label>
                <div v-if="!unitForm.inherits_venue_hours" class="space-y-2">
                    <div
                        v-for="day in unitForm.days"
                        :key="'u'+day.weekday"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-center gap-2 text-sm"
                    >
                        <span>{{ dayNames[day.weekday] }}</span>
                        <label class="flex items-center gap-1">
                            <input v-model="day.is_closed" type="checkbox" class="rounded border-wz-border" />
                            {{ t('availability.closed') }}
                        </label>
                        <input v-model="day.opens_at" type="time" class="wz-focus rounded-lg border border-wz-border bg-wz-elevated px-2 py-1" :disabled="day.is_closed" />
                        <input v-model="day.closes_at" type="time" class="wz-focus rounded-lg border border-wz-border bg-wz-elevated px-2 py-1" :disabled="day.is_closed" />
                    </div>
                </div>
                <Button type="submit" :disabled="unitForm.processing || !selectedUnit">{{ t('common.save') }}</Button>
            </form>

            <div class="wz-surface space-y-4 p-5">
                <h2 class="font-semibold">{{ t('availability.exceptions') }}</h2>
                <form class="space-y-3" @submit.prevent="saveException">
                    <Select v-model="exceptionForm.scope">
                        <template #label>{{ t('availability.scope') }}</template>
                        <option value="venue">{{ t('availability.scopeVenue') }}</option>
                        <option value="unit">{{ t('availability.scopeUnit') }}</option>
                    </Select>
                    <Select
                        v-if="exceptionForm.scope === 'unit'"
                        :model-value="exceptionForm.workspace_id ?? ''"
                        @update:model-value="exceptionForm.workspace_id = Number($event) || null"
                    >
                        <template #label>{{ t('owner.offerUnit') }}</template>
                        <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </Select>
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="exceptionForm.starts_on" type="date"><template #label>{{ t('availability.startsOn') }}</template></Input>
                        <Input v-model="exceptionForm.ends_on" type="date"><template #label>{{ t('availability.endsOn') }}</template></Input>
                    </div>
                    <Select v-model="exceptionForm.type">
                        <template #label>{{ t('availability.exceptionType') }}</template>
                        <option value="closed">{{ t('availability.typeClosed') }}</option>
                        <option value="special_hours">{{ t('availability.typeSpecial') }}</option>
                    </Select>
                    <div v-if="exceptionForm.type === 'special_hours'" class="grid grid-cols-2 gap-2">
                        <Input v-model="exceptionForm.opens_at" type="time"><template #label>{{ t('owner.openingTime') }}</template></Input>
                        <Input v-model="exceptionForm.closes_at" type="time"><template #label>{{ t('owner.closingTime') }}</template></Input>
                    </div>
                    <Input v-model="exceptionForm.reason"><template #label>{{ t('availability.reason') }}</template></Input>
                    <label v-if="conflicts.length" class="flex items-center gap-2 text-sm">
                        <input v-model="exceptionForm.acknowledge_conflicts" type="checkbox" class="rounded border-wz-border" />
                        {{ t('availability.acknowledge') }}
                    </label>
                    <Button type="submit" :disabled="exceptionForm.processing">{{ t('availability.addException') }}</Button>
                </form>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="ex in exceptions"
                        :key="ex.id"
                        class="flex items-center justify-between gap-2 rounded-lg border border-wz-border px-3 py-2"
                    >
                        <span>{{ ex.starts_on }} → {{ ex.ends_on }} · {{ ex.type }} · {{ ex.reason || '—' }}</span>
                        <Button size="sm" variant="ghost" @click="removeException(ex)">{{ t('common.delete') }}</Button>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
