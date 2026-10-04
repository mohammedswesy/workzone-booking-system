<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineProps({
  stats: Object,
  upcoming: Array,
})
</script>

<template>
  <AppLayout title="لوحة المستخدم">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">لوحة الحجوزات</h1>
        <Link :href="route('spaces.index')" class="bg-indigo-600 text-white px-4 py-2 rounded">تصفح المساحات</Link>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="border rounded-xl p-4 bg-white"><div class="text-slate-500 text-sm">كل الحجوزات</div><div class="text-2xl font-semibold">{{ stats.bookings_count }}</div></div>
        <div class="border rounded-xl p-4 bg-white"><div class="text-slate-500 text-sm">معلّقة</div><div class="text-2xl font-semibold">{{ stats.pending_count }}</div></div>
        <div class="border rounded-xl p-4 bg-white"><div class="text-slate-500 text-sm">مؤكدة</div><div class="text-2xl font-semibold">{{ stats.confirmed_count }}</div></div>
        <div class="border rounded-xl p-4 bg-white"><div class="text-slate-500 text-sm">غير مدفوعة</div><div class="text-2xl font-semibold">{{ stats.unpaid_count }}</div></div>
      </div>

      <div class="border rounded-xl bg-white">
        <div class="px-4 py-3 border-b font-medium">القادمة</div>
        <div v-if="!upcoming?.length" class="p-6 text-slate-500">لا توجد حجوزات قادمة.</div>
        <ul v-else class="divide-y">
          <li v-for="b in upcoming" :key="b.id" class="px-4 py-3 flex items-center justify-between gap-3">
            <div>
              <div class="font-medium">{{ b.workspace?.name }}</div>
              <div class="text-sm text-slate-500">{{ b.start_at ? new Date(b.start_at).toLocaleString() : '' }}</div>
            </div>
            <Link :href="route('user.bookings.show', b.id)" class="text-indigo-600 text-sm">عرض</Link>
          </li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
