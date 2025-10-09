<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import { reactive, watch, computed } from 'vue'

const props = defineProps({
  offers: { type: Object, required: true }, // paginator { data, links, ... }
  filters: { type: Object, default: () => ({ search: '', per_page: 12 }) },
})

const form = reactive({
  search: props.filters?.search ?? '',
  per_page: props.filters?.per_page ?? 12,
})

function apply() {
  router.get(route('owner.offers.index'), { ...form }, { preserveState: true, replace: true })
}
watch(() => form.per_page, apply)

function destroyOffer(id) {
  if (!confirm('حذف هذا العرض؟')) return
  router.delete(route('owner.offers.destroy', id), { preserveScroll: true })
}

const hasData = computed(() => props.offers?.data?.length)
</script>

<template>
  <AppLayout title="العروض">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
      <!-- Header / actions -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">العروض</h1>

        <div class="flex items-center gap-2">
          <input
            v-model="form.search"
            @keyup.enter="apply"
            placeholder="بحث بالعنوان…"
            class="border rounded px-3 py-2 w-64"
          />
          <select v-model.number="form.per_page" class="border rounded px-2 py-2">
            <option :value="6">6</option>
            <option :value="12">12</option>
            <option :value="24">24</option>
          </select>
          <button @click="apply" class="px-3 py-2 border rounded hover:bg-gray-50">تطبيق</button>

          <Link
            :href="route('owner.offers.create')"
            class="bg-indigo-600 text-white px-3 py-2 rounded"
          >
            إضافة عرض
          </Link>
        </div>
      </div>

      <!-- Empty -->
      <div v-if="!hasData" class="border rounded p-10 text-center text-slate-600">
        لا توجد عروض بعد.
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto border rounded">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-700">
            <tr>
              <th class="px-3 py-2 text-right">#</th>
              <th class="px-3 py-2 text-right">العنوان</th>
              <th class="px-3 py-2 text-right">المساحة</th>
              <th class="px-3 py-2 text-right">% الخصم</th>
              <th class="px-3 py-2 text-right">الفترة</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in offers.data" :key="o.id" class="border-t">
              <td class="px-3 py-2">{{ o.id }}</td>
              <td class="px-3 py-2 font-medium">{{ o.title }}</td>
              <td class="px-3 py-2">{{ o.workspace?.name ?? '—' }}</td>
              <td class="px-3 py-2">{{ o.discount_percent }}%</td>
              <td class="px-3 py-2">
                {{ new Date(o.starts_at).toLocaleDateString() }} — {{ new Date(o.ends_at).toLocaleDateString() }}
              </td>
              <td class="px-3 py-2">
                <span
                  class="px-2 py-1 rounded text-xs"
                  :class="o.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                >
                  {{ o.is_active ? 'فعّال' : 'متوقف' }}
                </span>
              </td>
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <Link
                    :href="route('owner.offers.edit', o.id)"
                    class="px-2 py-1 border rounded hover:bg-gray-50"
                  >
                    تعديل
                  </Link>
                  <button
                    class="px-2 py-1 border rounded text-red-600 hover:bg-red-50"
                    @click="destroyOffer(o.id)"
                  >
                    حذف
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination v-if="offers?.links" :links="offers.links" />
    </div>
  </AppLayout>
</template>
