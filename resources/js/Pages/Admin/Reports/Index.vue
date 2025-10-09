<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'

const props = defineProps({
  filters: { type: Object, default: () => ({ from: null, to: null, status: '' }) },
  kpis:    { type: Object, default: () => ({ users:0, owners:0, workspaces:0, bookings:0, revenue:0 }) },
  series:  { type: Array,  default: () => [] }, // [{d:'2025-10-05', c:4}, ...]
  topWorkspaces: { type: Array, default: () => [] }, // [{id,name,count,sum}]
})

const form = reactive({
  from: props.filters.from || '',
  to: props.filters.to || '',
  status: props.filters.status || '',
})

function apply() {
  router.get(route('admin.reports.index'), { ...form }, { preserveState: true, replace: true })
}

const totalDays = computed(() => props.series?.length ?? 0)
const totalInRange = computed(() => (props.series || []).reduce((a,b)=>a + Number(b.c||0), 0))
</script>

<template>
  <AppLayout title="تقارير الإدارة">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8">

      <!-- فلاتر -->
      <div class="bg-white border rounded-2xl p-4 flex flex-wrap items-end gap-3">
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">من تاريخ</label>
          <input v-model="form.from" type="date" class="border rounded px-3 py-2">
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">إلى تاريخ</label>
          <input v-model="form.to" type="date" class="border rounded px-3 py-2">
        </div>
        <div class="grid gap-1">
          <label class="text-xs text-slate-600">الحالة</label>
          <select v-model="form.status" class="border rounded px-3 py-2">
            <option value="">الكل</option>
            <option value="pending">معلّق</option>
            <option value="paid">مدفوع</option>
            <option value="cancelled">ملغى</option>
          </select>
        </div>
        <button @click="apply" class="px-4 py-2 rounded-xl bg-gray-900 text-white hover:bg-gray-800">
          تطبيق
        </button>
      </div>

      <!-- KPIs -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white border rounded-2xl p-4">
          <div class="text-xs text-slate-500">Users</div>
          <div class="text-2xl font-bold">{{ kpis.users }}</div>
        </div>
        <div class="bg-white border rounded-2xl p-4">
          <div class="text-xs text-slate-500">Owners</div>
          <div class="text-2xl font-bold">{{ kpis.owners }}</div>
        </div>
        <div class="bg-white border rounded-2xl p-4">
          <div class="text-xs text-slate-500">Workspaces</div>
          <div class="text-2xl font-bold">{{ kpis.workspaces }}</div>
        </div>
        <div class="bg-white border rounded-2xl p-4">
          <div class="text-xs text-slate-500">Bookings</div>
          <div class="text-2xl font-bold">{{ kpis.bookings }}</div>
        </div>
        <div class="bg-white border rounded-2xl p-4">
          <div class="text-xs text-slate-500">Revenue (paid)</div>
          <div class="text-2xl font-bold">${{ Number(kpis.revenue ?? 0).toFixed(2) }}</div>
        </div>
      </div>

      <!-- سلسلة زمنية بسيطة -->
      <div class="bg-white border rounded-2xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">الحجوزات يوميًا</h2>
          <div class="text-sm text-slate-600">
            الأيام: {{ totalDays }} • المجموع: {{ totalInRange }}
          </div>
        </div>

        <div v-if="!series?.length" class="text-slate-500 text-sm">
          لا توجد بيانات ضمن النطاق المحدد.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="row in series"
            :key="row.d"
            class="flex items-center gap-3"
          >
            <div class="w-28 text-xs text-slate-600">{{ row.d }}</div>
            <div class="flex-1 h-2 bg-slate-100 rounded">
              <div
                class="h-2 bg-indigo-600 rounded"
                :style="{ width: Math.min(100, (row.c / Math.max(...series.map(s=>s.c))) * 100) + '%' }"
                title="عدد الحجوزات"
              />
            </div>
            <div class="w-8 text-right text-sm font-medium">{{ row.c }}</div>
          </div>
        </div>
      </div>

      <!-- أعلى المساحات حجزًا -->
      <div class="bg-white border rounded-2xl p-4">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-lg font-semibold">أعلى المساحات حجزًا</h2>
        </div>

        <div v-if="!topWorkspaces?.length" class="text-slate-500 text-sm">
          لا توجد نتائج مطابقة.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-3 py-2 text-right">#</th>
                <th class="px-3 py-2 text-right">المساحة</th>
                <th class="px-3 py-2 text-right">عدد الحجوزات</th>
                <th class="px-3 py-2 text-right">الإيراد</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(w, i) in topWorkspaces" :key="w.id" class="border-t">
                <td class="px-3 py-2">{{ i + 1 }}</td>
                <td class="px-3 py-2 font-medium">{{ w.name }}</td>
                <td class="px-3 py-2">{{ w.count }}</td>
                <td class="px-3 py-2">${{ Number(w.sum ?? 0).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
