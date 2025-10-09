<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'

const props = defineProps({
  bookings: { type: Object, required: true }, // Laravel paginator
  filters: {
    type: Object,
    default: () => ({
      status: '',       // '', 'pending', 'paid', 'cancelled'
      search: '',       // by user/workspace
      per_page: 12
    })
  }
})

const form = reactive({
  status: props.filters.status || '',
  search: props.filters.search || '',
  per_page: props.filters.per_page || 12,
})

function applyFilters() {
  router.get(route('owner.bookings.index'), { ...form }, { preserveState: true, replace: true })
}

watch(() => form.per_page, () => applyFilters())

// تحديث حالة الحجز
function updateStatus(bookingId, status) {
  if (status === 'cancelled' && !confirm('تأكيد إلغاء الحجز؟')) return
  router.put(route('owner.bookings.update', bookingId), { status }, {
    preserveScroll: true,
  })
}

const hasData = computed(() => props.bookings?.data?.length)
</script>

<template>
  <AppLayout title="حجوزات مساحاتي">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">حجوزات مساحاتي</h1>

        <div class="flex items-center gap-2">
          <input
            v-model="form.search"
            @keyup.enter="applyFilters"
            class="border rounded px-3 py-2"
            placeholder="بحث (اسم المستخدم/المساحة)"
          />
          <select v-model.number="form.per_page" class="border rounded px-2 py-2">
            <option :value="6">6</option>
            <option :value="12">12</option>
            <option :value="24">24</option>
          </select>
          <button @click="applyFilters" class="px-3 py-2 border rounded hover:bg-gray-50">
            تطبيق
          </button>
        </div>
      </div>

      <!-- Tabs للحالات -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          class="px-3 py-1 rounded border"
          :class="form.status === '' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50'"
          @click="form.status=''; applyFilters()"
        >
          الكل
        </button>
        <button
          class="px-3 py-1 rounded border"
          :class="form.status === 'pending' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50'"
          @click="form.status='pending'; applyFilters()"
        >
          معلّق
        </button>
        <button
          class="px-3 py-1 rounded border"
          :class="form.status === 'paid' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50'"
          @click="form.status='paid'; applyFilters()"
        >
          مدفوع
        </button>
        <button
          class="px-3 py-1 rounded border"
          :class="form.status === 'cancelled' ? 'bg-gray-900 text-white' : 'hover:bg-gray-50'"
          @click="form.status='cancelled'; applyFilters()"
        >
          مُلغى
        </button>
      </div>

      <div v-if="!hasData" class="text-slate-600 py-12 text-center border rounded">
        لا يوجد حجوزات مطابقة.
      </div>

      <div v-else class="overflow-x-auto border rounded-lg">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-700">
            <tr>
              <th class="px-3 py-2 text-right">#</th>
              <th class="px-3 py-2 text-right">المساحة</th>
              <th class="px-3 py-2 text-right">العميل</th>
              <th class="px-3 py-2 text-right">الساعات</th>
              <th class="px-3 py-2 text-right">الإجمالي</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">أنشئ</th>
              <th class="px-3 py-2 text-right">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in bookings.data" :key="b.id" class="border-t">
              <td class="px-3 py-2">{{ b.id }}</td>
              <td class="px-3 py-2">
                <div class="font-medium">{{ b.workspace?.name ?? '—' }}</div>
              </td>
              <td class="px-3 py-2">
                <div class="font-medium">{{ b.user?.name ?? '—' }}</div>
                <div class="text-slate-500 text-xs">{{ b.user?.email ?? '' }}</div>
              </td>
              <td class="px-3 py-2">{{ b.hours }}</td>
              <td class="px-3 py-2">$ {{ Number(b.total_price ?? 0).toFixed(2) }}</td>
              <td class="px-3 py-2">
                <span
                  class="px-2 py-1 rounded text-xs"
                  :class="{
                    'bg-yellow-100 text-yellow-700': b.status === 'pending',
                    'bg-green-100 text-green-700': b.status === 'paid',
                    'bg-gray-100 text-gray-700': b.status === 'cancelled'
                  }"
                >
                  {{ b.status }}
                </span>
              </td>
              <td class="px-3 py-2">{{ new Date(b.created_at).toLocaleString() }}</td>
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <button
                    v-if="b.status === 'pending'"
                    class="px-2 py-1 border rounded text-green-700 hover:bg-green-50"
                    @click="updateStatus(b.id, 'paid')"
                  >
                    إتمام الدفع
                  </button>
                  <button
                    v-if="b.status !== 'cancelled'"
                    class="px-2 py-1 border rounded text-red-700 hover:bg-red-50"
                    @click="updateStatus(b.id, 'cancelled')"
                  >
                    إلغاء
                  </button>
                  <Link
                    :href="route('owner.bookings.show', b.id)"
                    class="px-2 py-1 border rounded hover:bg-gray-50"
                  >
                    تفاصيل
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination v-if="bookings?.links" :links="bookings.links" />
    </div>
  </AppLayout>
</template>
