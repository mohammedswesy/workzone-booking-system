<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
const props = defineProps({ booking: Object })
function setStatus(s){ router.put(route('admin.bookings.update', props.booking.id), { status: s }) }
</script>

<template>
  <AppLayout :title="`حجز #${booking.id}`">
    <div class="max-w-3xl mx-auto px-4 py-6 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">الحجز #{{ booking.id }}</h1>
        <Link :href="route('admin.bookings.index')" class="px-3 py-2 border rounded hover:bg-gray-50">رجوع</Link>
      </div>

      <div class="border rounded-xl p-4 grid sm:grid-cols-2 gap-4 bg-white">
        <div><div class="text-slate-500 text-sm">المساحة</div><div class="font-medium">{{ booking.workspace?.name }}</div></div>
        <div><div class="text-slate-500 text-sm">العميل</div><div class="font-medium">{{ booking.user?.name }}</div></div>
        <div><div class="text-slate-500 text-sm">الساعات</div><div class="font-medium">{{ booking.hours }}</div></div>
        <div><div class="text-slate-500 text-sm">الإجمالي</div><div class="font-medium">$ {{ Number(booking.total_price ?? 0).toFixed(2) }}</div></div>
        <div><div class="text-slate-500 text-sm">الحالة</div><div class="font-medium">{{ booking.status }}</div></div>
      </div>

      <div class="flex gap-2">
        <button class="px-3 py-2 border rounded hover:bg-gray-50" @click="setStatus('paid')">تحديد كمدفوع</button>
        <button class="px-3 py-2 border rounded text-red-600 hover:bg-red-50" @click="setStatus('cancelled')">إلغاء</button>
      </div>
    </div>
  </AppLayout>
</template>
