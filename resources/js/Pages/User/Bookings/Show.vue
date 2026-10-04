<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  booking: { type: Object, required: true },
})

const manualForm = useForm({
  proof: null,
})

function onProofChange(e) {
  manualForm.proof = e.target.files[0] ?? null
}

function submitManual() {
  manualForm.post(route('user.payments.manual.store', props.booking.id), {
    forceFormData: true,
  })
}

function payWithPaypal() {
  useForm({}).post(route('user.payments.paypal.store', props.booking.id))
}
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
          <span class="px-2 py-1 rounded text-xs bg-slate-100">{{ booking.status }}</span>
        </div>
        <div>
          <div class="text-slate-500 text-sm">الدفع</div>
          <span class="px-2 py-1 rounded text-xs bg-slate-100">{{ booking.payment_status }}</span>
        </div>
        <div>
          <div class="text-slate-500 text-sm">الإجمالي</div>
          <div class="font-medium">$ {{ Number(booking.total_price ?? 0).toFixed(2) }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-sm">من</div>
          <div class="font-medium">{{ booking.start_at ? new Date(booking.start_at).toLocaleString() : '—' }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-sm">إلى</div>
          <div class="font-medium">{{ booking.end_at ? new Date(booking.end_at).toLocaleString() : '—' }}</div>
        </div>
      </div>

      <div
        v-if="booking.status === 'pending' && booking.payment_status !== 'paid'"
        class="bg-white border rounded-xl p-4 space-y-3"
      >
        <h2 class="font-semibold">الدفع</h2>
        <form @submit.prevent="submitManual" class="space-y-2">
          <label class="block text-sm text-slate-600">رفع إثبات تحويل (يدوي)</label>
          <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="onProofChange" />
          <div v-if="manualForm.errors.proof" class="text-sm text-red-600">{{ manualForm.errors.proof }}</div>
          <button class="bg-indigo-600 text-white px-4 py-2 rounded" :disabled="manualForm.processing">
            إرسال الإثبات
          </button>
        </form>
        <button class="px-4 py-2 border rounded hover:bg-gray-50" type="button" @click="payWithPaypal">
          الدفع عبر PayPal
        </button>
      </div>
    </div>
  </AppLayout>
</template>
