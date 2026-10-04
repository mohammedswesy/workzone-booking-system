<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  stats: {
    type: Object,
    default: () => ({
      workspaces_count: 0,
      bookings_count: 0,
      pending_count: 0,
      active_offers_count: 0,
      revenue: '0',
    }),
  },
  topWorkspaces: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <AppLayout title="لوحة المالك">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">لوحة المالك</h1>
          <p class="text-slate-600 text-sm">إحصائيات مساحاتك وحجوزاتك فقط.</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('owner.workspaces.index')" class="px-4 py-2 rounded-xl border bg-white">المساحات</Link>
          <Link :href="route('owner.offers.create')" class="px-4 py-2 rounded-xl bg-indigo-600 text-white">أضف عرضًا</Link>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="border rounded-2xl p-5 bg-white"><div class="text-slate-500 text-sm">المساحات</div><div class="text-3xl font-bold">{{ stats?.workspaces_count ?? 0 }}</div></div>
        <div class="border rounded-2xl p-5 bg-white"><div class="text-slate-500 text-sm">الحجوزات</div><div class="text-3xl font-bold">{{ stats?.bookings_count ?? 0 }}</div></div>
        <div class="border rounded-2xl p-5 bg-white"><div class="text-slate-500 text-sm">معلّقة</div><div class="text-3xl font-bold">{{ stats?.pending_count ?? 0 }}</div></div>
        <div class="border rounded-2xl p-5 bg-white"><div class="text-slate-500 text-sm">عروض فعّالة</div><div class="text-3xl font-bold">{{ stats?.active_offers_count ?? 0 }}</div></div>
        <div class="border rounded-2xl p-5 bg-white"><div class="text-slate-500 text-sm">الإيرادات</div><div class="text-3xl font-bold">${{ Number(stats?.revenue ?? 0).toFixed(2) }}</div></div>
      </div>

      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">أعلى المساحات حجزًا</h2>
          <Link :href="route('owner.bookings.index')" class="text-indigo-600 hover:underline">كل الحجوزات →</Link>
        </div>

        <div v-if="!topWorkspaces?.length" class="border rounded-2xl p-6 text-slate-600 bg-white">
          لا توجد حجوزات بعد على مساحاتك.
        </div>

        <div v-else class="bg-white border rounded-2xl divide-y">
          <div v-for="w in topWorkspaces" :key="w.id" class="px-4 py-3 flex items-center justify-between gap-3">
            <div>
              <div class="font-medium">{{ w.name }}</div>
              <div class="text-sm text-slate-500">{{ w.bookings_count }} حجز</div>
            </div>
            <div class="font-semibold">${{ Number(w.revenue || 0).toFixed(2) }}</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
