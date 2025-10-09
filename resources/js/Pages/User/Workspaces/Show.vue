<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  workspace: { type: Object, required: true },
  can_book: { type: Boolean, default: false },
  pending_booking_id: { type: Number, default: null }
})

function goToBooking() {
  window.location.href = route('booking.create', { workspace_id: props.workspace.id })
}
</script>

<template>
  <AppLayout :title="workspace.name">
    <div class="max-w-5xl mx-auto px-4 py-6 grid md:grid-cols-2 gap-6">
      <div>
        <img
          :src="workspace.image_url || 'https://placehold.co/800x500?text=Workspace'"
          alt=""
          class="w-full h-64 md:h-96 object-cover rounded-xl border"
        />
      </div>

      <div class="space-y-3">
        <div class="flex items-start justify-between gap-2">
          <h1 class="text-2xl font-bold">{{ workspace.name }}</h1>
          <span
            v-if="workspace.offer_label || (workspace.active_discount_percent ?? 0) > 0"
            class="text-xs bg-rose-100 text-rose-700 px-2 py-0.5 rounded"
          >
            {{ workspace.offer_label ?? ('خصم ' + workspace.active_discount_percent + '%') }}
          </span>
        </div>

        <div class="text-slate-600">{{ workspace.location }}</div>

        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="border rounded p-3">
            <div class="text-slate-500">السعة</div>
            <div class="font-semibold">{{ workspace.capacity }}</div>
          </div>

          <div class="border rounded p-3">
            <div class="text-slate-500">السعر/الساعة</div>

            <template v-if="(workspace.active_discount_percent ?? 0) > 0 && workspace.effective_price_per_hour < workspace.price_per_hour">
              <div class="flex items-baseline gap-2">
                <span class="line-through text-gray-400 text-sm">
                  ${{ Number(workspace.price_per_hour).toFixed(2) }}
                </span>
                <span class="font-semibold text-lg">
                  ${{ Number(workspace.effective_price_per_hour).toFixed(2) }}
                </span>
              </div>
            </template>

            <template v-else>
              <div class="font-semibold">
                $ {{ Number(workspace.price_per_hour ?? 0).toFixed(2) }}
              </div>
            </template>
          </div>
        </div>

        <div class="text-xs text-slate-500">
          أضيفت بتاريخ: {{ new Date(workspace.created_at).toLocaleDateString() }}
        </div>

        <div class="flex items-center gap-3 pt-3">
          <template v-if="can_book">
            <button
              v-if="!pending_booking_id"
              class="bg-gray-900 text-white px-4 py-2 rounded"
              @click="goToBooking"
            >
              احجز الآن
            </button>

            <Link
              v-else
              :href="route('user.bookings.show', pending_booking_id)"
              class="px-4 py-2 rounded border text-yellow-700 hover:bg-yellow-50"
            >
              لديك حجز معلّق — عرض الحجز
            </Link>
          </template>

          <Link href="/spaces" class="px-4 py-2 rounded border hover:bg-gray-50">
            رجوع
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
