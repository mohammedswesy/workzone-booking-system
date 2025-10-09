<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  booking: { type: Object, required: true }, // booking مع user, workspace
})
</script>

<template>
  <AppLayout :title="`تفاصيل الحجز #${booking.id}`">
    <div class="max-w-3xl mx-auto px-4 py-6 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">تفاصيل الحجز #{{ booking.id }}</h1>
        <Link :href="route('user.bookings.index')" class="px-3 py-2 border rounded hover:bg-gray-50">رجوع</Link>
      </div>

      <div class="grid sm:grid-cols-2 gap-4 bg-white border rounded-xl p-4">
        <div>
          <div class="text-slate-500 text-sm">المساحة</div>
          <div class="font-medium">{{ booking.workspace?.name }}</div>
          <div class="text-slate-600 text-sm">{{ booking.workspace?.location }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-sm">الحالة</div>
          <span class="px-2 py-1 rounded text-xs"
                :class="{
                  'bg-yellow-100 text-yellow-700': booking.status==='pending',
                  'bg-green-100 text-green-700': booking.status==='paid',
                  'bg-gray-100  text-gray-700' : booking.status==='cancelled'
                }">
            {{ booking.status }}
          </span>
        </div>
        <div>
          <div class="text-slate-500 text-sm">الساعات</div>
          <div class="font-medium">{{ booking.hours }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-sm">الإجمالي</div>
          <div class="font-medium">$ {{ Number(booking.total_price ?? 0).toFixed(2) }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-sm">أنشئ</div>
          <div class="font-medium">{{ new Date(booking.created_at).toLocaleString() }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
