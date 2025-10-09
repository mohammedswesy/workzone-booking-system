<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  booking: { type: Object, required: true }
})
</script>

<template>
  <AppLayout title="تفاصيل الحجز">
    <div class="max-w-3xl mx-auto px-4 py-6 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">تفاصيل الحجز #{{ booking.id }}</h1>
        <div class="flex gap-2">
          <Link :href="route('owner.bookings.edit', booking.id)" class="px-3 py-2 border rounded hover:bg-gray-50">
            تعديل
          </Link>
          <Link :href="route('owner.bookings.index')" class="px-3 py-2 border rounded hover:bg-gray-50">
            رجوع
          </Link>
        </div>
      </div>

      <div class="border rounded-xl p-4 grid sm:grid-cols-2 gap-4">
        <div>
          <div class="text-slate-500 text-sm">المساحة</div>
          <div class="font-medium">{{ booking.workspace?.name ?? '-' }}</div>
          <div class="text-slate-500 text-sm mt-1">{{ booking.workspace?.location ?? '' }}</div>
        </div>

        <div>
          <div class="text-slate-500 text-sm">العميل</div>
          <div class="font-medium">{{ booking.user?.name ?? '-' }}</div>
          <div class="text-slate-500 text-sm mt-1">{{ booking.user?.email ?? '' }}</div>
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
          <div class="text-slate-500 text-sm">الحالة</div>
          <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs border"
                :class="{
                  'bg-yellow-50 border-yellow-300 text-yellow-700': booking.status==='pending',
                  'bg-green-50 border-green-300 text-green-700': booking.status==='paid',
                  'bg-red-50 border-red-300 text-red-700': booking.status==='cancelled'
                }">
            {{ booking.status }}
          </span>
        </div>

        <div>
          <div class="text-slate-500 text-sm">أنشئ في</div>
          <div class="font-medium">{{ booking.created_at }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
