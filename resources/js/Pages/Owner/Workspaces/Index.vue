<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Ui/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
  spaces: { type: Object, required: true }, // { data, links, ... }
  filters: { type: Object, default: () => ({ search: '', per_page: 9 }) }
})

const search   = ref(props.filters?.search ?? '')
const perPage  = ref(props.filters?.per_page ?? 9)

function applyFilters() {
  router.get(
    route('owner.workspaces.index'),
    { search: search.value || undefined, per_page: perPage.value || undefined },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// اختياري: نفّذ البحث بعد 400ms من توقف الكتابة
let t
watch(search, () => {
  clearTimeout(t)
  t = setTimeout(applyFilters, 400)
})

// حذف مساحة
function destroySpace(id) {
  if (!confirm('حذف هذه المساحة؟')) return
  router.delete(route('owner.workspaces.destroy', id), {
    preserveScroll: true,
    onSuccess: () => {
      // ممكن تظهر Toast/تنبيه نجاح هنا
    }
  })
}
</script>

<template>
  <AppLayout title="مساحاتي">
    <div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">مساحاتي</h1>
        <div class="flex items-center gap-2">
          <input
            v-model="search"
            type="text"
            placeholder="ابحث بالاسم أو الموقع…"
            class="border rounded px-3 py-2 w-52"
          />
          <select v-model="perPage" @change="applyFilters" class="border rounded px-2 py-2">
            <option :value="6">6</option>
            <option :value="9">9</option>
            <option :value="12">12</option>
            <option :value="15">15</option>
          </select>

          <Link
            :href="route('owner.workspaces.create')"
            class="px-3 py-2 border rounded hover:bg-gray-50"
          >
            إضافة مساحة
          </Link>
        </div>
      </div>

      <div v-if="!spaces?.data?.length" class="text-slate-600 py-10 text-center">
        لا يوجد مساحات بعد. ابدأ بإضافة أول مساحة 👇
      </div>

      <div v-else class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
        <div
          v-for="s in spaces.data"
          :key="s.id"
          class="border rounded-xl p-3 flex flex-col"
        >
          <img
            :src="s.image_url || 'https://placehold.co/600x400?text=Workspace'"
            :alt="s.name"
            class="w-full h-32 object-cover rounded mb-2"
          />
          <div class="font-semibold">{{ s.name }}</div>
          <div class="text-slate-600">{{ s.location }}</div>
          <div class="text-slate-700 mt-1">السعة: {{ s.capacity }}</div>
          <div class="text-slate-700">$ {{ Number(s.price_per_hour).toFixed(2) }}/h</div>

          <div class="mt-3 flex gap-2">
            <Link
              :href="route('owner.workspaces.edit', s.id)"
              class="px-3 py-1 border rounded hover:bg-gray-50"
            >
              تعديل
            </Link>
            <button
              type="button"
              class="px-3 py-1 border rounded text-red-600 hover:bg-red-50"
              @click="destroySpace(s.id)"
            >
              حذف
            </button>
          </div>
        </div>
      </div>

      <Pagination v-if="spaces?.links" :links="spaces.links" />
    </div>
  </AppLayout>
</template>
