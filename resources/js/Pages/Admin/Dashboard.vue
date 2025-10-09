<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      users: 0,
      owners: 0,
      workspaces: 0,
      bookings: 0,
      pending_bookings: 0, // اختياري
      paid_bookings: 0,    // اختياري
    })
  },
  // اختياري: لو حابب تعرض آخر العناصر
  lastUsers:   { type: Array, default: () => [] },
  lastSpaces:  { type: Array, default: () => [] },
  lastBookings:{ type: Array, default: () => [] },
})

const kpis = computed(() => ([
  {
    label: 'Users',
    value: props.stats.users ?? 0,
    hint: 'إجمالي المستخدمين',
    icon: '👥',
  },
  {
    label: 'Owners',
    value: props.stats.owners ?? 0,
    hint: 'مالكو المساحات',
    icon: '🏢',
  },
  {
    label: 'Workspaces',
    value: props.stats.workspaces ?? 0,
    hint: 'عدد المساحات',
    icon: '🧩',
  },
  {
    label: 'Bookings',
    value: props.stats.bookings ?? 0,
    hint: 'كل الحجوزات',
    icon: '🧾',
  },
]))

const hasRecent = computed(() =>
  (props.lastUsers?.length || props.lastSpaces?.length || props.lastBookings?.length)
)
</script>

<template>
  <AppLayout title="لوحة التحكم">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8">

      <!-- Hero / Header -->
      <section
        class="rounded-3xl p-6 md:p-8 bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 text-white relative overflow-hidden"
      >
        <div class="absolute -right-20 -top-20 w-72 h-72 rounded-full opacity-20 blur-3xl bg-white/10"></div>
        <div class="absolute -left-24 -bottom-24 w-80 h-80 rounded-full opacity-20 blur-3xl bg-white/10"></div>

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 relative z-10">
          <div>
            <h1 class="text-2xl md:text-3xl font-extrabold">لوحة WorkZone</h1>
            <p class="text-slate-200/90 mt-1">نظرة سريعة على الأداء العام للنظام.</p>
          </div>

          <!-- أزرار سريعة -->
          <div class="flex flex-wrap gap-2">
            <Link href="/admin/users"
                  class="px-4 py-2 rounded-xl bg-white text-slate-900 hover:bg-slate-100">
              إدارة المستخدمين
            </Link>
            <Link href="/admin/workspaces"
                  class="px-4 py-2 rounded-xl bg-white/10 ring-1 ring-white/20 hover:bg-white/15">
              إدارة المساحات
            </Link>
            <Link href="/admin/bookings"
                  class="px-4 py-2 rounded-xl bg-white/10 ring-1 ring-white/20 hover:bg-white/15">
              إدارة الحجوزات
            </Link>
          </div>
        </div>
      </section>

      <!-- KPI Cards -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="card in kpis"
          :key="card.label"
          class="group rounded-2xl border bg-white p-5 hover:shadow-[0_10px_30px_-10px_rgba(0,0,0,.15)] transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm text-slate-500">{{ card.hint }}</div>
            <div class="text-xl leading-none select-none">{{ card.icon }}</div>
          </div>
          <div class="mt-1 text-3xl font-extrabold tabular-nums">{{ card.value }}</div>
          <div class="mt-1 text-slate-500 text-sm">{{ card.label }}</div>
        </div>

        <!-- بطاقتا حالة الحجوزات (اختياري لو القيم موجودة) -->
        <div
          v-if="props.stats.pending_bookings !== undefined"
          class="rounded-2xl border bg-white p-5"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm text-slate-500">حجوزات معلّقة</div>
            <div class="text-xl">⏳</div>
          </div>
          <div class="mt-1 text-3xl font-extrabold tabular-nums">
            {{ props.stats.pending_bookings }}
          </div>
          <div class="mt-1">
            <span class="text-xs px-2 py-1 rounded bg-amber-100 text-amber-700">
              بإنتظار الإجراء
            </span>
          </div>
        </div>

        <div
          v-if="props.stats.paid_bookings !== undefined"
          class="rounded-2xl border bg-white p-5"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm text-slate-500">حجوزات مدفوعة</div>
            <div class="text-xl">✅</div>
          </div>
          <div class="mt-1 text-3xl font-extrabold tabular-nums">
            {{ props.stats.paid_bookings }}
          </div>
          <div class="mt-1">
            <span class="text-xs px-2 py-1 rounded bg-green-100 text-green-700">
              مكتملة الدفع
            </span>
          </div>
        </div>
      </section>

      <!-- Quick panels -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- آخر المستخدمين -->
        <div class="rounded-2xl border bg-white overflow-hidden">
          <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold">آخر المستخدمين</h3>
            <Link href="/admin/users" class="text-sm text-indigo-600 hover:underline">الكل →</Link>
          </div>
          <div v-if="!lastUsers.length" class="p-6 text-slate-500 text-sm">لا يوجد بيانات لعرضها.</div>
          <ul v-else class="divide-y">
            <li v-for="u in lastUsers" :key="u.id" class="px-4 py-3 flex items-center justify-between">
              <div>
                <div class="font-medium">{{ u.name }}</div>
                <div class="text-xs text-slate-500">{{ u.email }}</div>
              </div>
              <span class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-700">
                {{ (u.role || 'user').toUpperCase() }}
              </span>
            </li>
          </ul>
        </div>

        <!-- آخر المساحات -->
        <div class="rounded-2xl border bg-white overflow-hidden">
          <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold">آخر المساحات</h3>
            <Link href="/admin/workspaces" class="text-sm text-indigo-600 hover:underline">الكل →</Link>
          </div>
          <div v-if="!lastSpaces.length" class="p-6 text-slate-500 text-sm">لا يوجد بيانات لعرضها.</div>
          <ul v-else class="divide-y">
            <li v-for="w in lastSpaces" :key="w.id" class="px-4 py-3 flex items-center justify-between">
              <div>
                <div class="font-medium">{{ w.name }}</div>
                <div class="text-xs text-slate-500">{{ w.location }}</div>
              </div>
              <div class="text-sm font-semibold">${{ Number(w.price_per_hour ?? 0).toFixed(2) }}/h</div>
            </li>
          </ul>
        </div>

        <!-- آخر الحجوزات -->
        <div class="rounded-2xl border bg-white overflow-hidden">
          <div class="p-4 border-b flex items-center justify-between">
            <h3 class="font-semibold">آخر الحجوزات</h3>
            <Link href="/admin/bookings" class="text-sm text-indigo-600 hover:underline">الكل →</Link>
          </div>
          <div v-if="!lastBookings.length" class="p-6 text-slate-500 text-sm">لا يوجد بيانات لعرضها.</div>
          <ul v-else class="divide-y">
            <li v-for="b in lastBookings" :key="b.id" class="px-4 py-3">
              <div class="flex items-center justify-between">
                <div class="font-medium">#{{ b.id }} — {{ b.workspace?.name ?? '—' }}</div>
                <span
                  class="text-xs px-2 py-1 rounded"
                  :class="{
                    'bg-yellow-100 text-yellow-700': b.status === 'pending',
                    'bg-green-100 text-green-700': b.status === 'paid',
                    'bg-slate-100 text-slate-700': !['pending','paid'].includes(b.status)
                  }"
                >
                  {{ b.status }}
                </span>
              </div>
              <div class="text-xs text-slate-500 mt-1">
                {{ b.user?.name ?? '—' }} • ${{ Number(b.total_price ?? 0).toFixed(2) }}
              </div>
            </li>
          </ul>
        </div>
      </section>

      <!-- Empty state hint -->
      <section v-if="!hasRecent" class="rounded-2xl border bg-white p-6">
        <div class="text-slate-600 text-sm">
          يمكنك تمرير قوائم <code>lastUsers</code>، <code>lastSpaces</code>، <code>lastBookings</code> من الراوت
          لعرض أحدث النشاطات هنا.
        </div>
      </section>
    </div>
  </AppLayout>
</template>
