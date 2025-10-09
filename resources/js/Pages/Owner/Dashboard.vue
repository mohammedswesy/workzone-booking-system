<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      workspaces_count: 0,
      bookings_count: 0,
      pending_count: 0,
      active_offers_count: 0,
    }),
  },
  topDiscounted: {
    type: Array,
    default: () => [],
  },
})

function discountedPrice(price_per_hour, max_discount) {
  if (!max_discount || max_discount <= 0) return Number(price_per_hour ?? 0).toFixed(2)
  const p = Number(price_per_hour ?? 0)
  const d = Number(max_discount ?? 0)
  return (p * (1 - d / 100)).toFixed(2)
}
</script>

<template>
  <AppLayout title="لوحة المالك">
    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8">

      <!-- شريط بارز للعروض -->
      <div
        class="rounded-2xl p-4 sm:p-6 border bg-gradient-to-r from-indigo-50 to-blue-50 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <div>
          <div class="text-sm text-slate-700">عروضك الفعّالة الآن</div>
          <div class="text-2xl font-extrabold">
            {{ stats?.active_offers_count ?? 0 }} عرض
          </div>
          <div class="text-slate-600 text-sm">
            زد ظهور مساحاتك بعمل عروض موقّتة وخصومات جذّابة.
          </div>
        </div>

        <div class="flex gap-2">
          <!-- لو عندك صفحات للعروض، استبدل الروابط بأسامي الراوت المناسبة -->
          <Link
            href="/owner/workspaces"
            class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50"
          >
            إدارة المساحات
          </Link>
          <Link
    :href="route('owner.offers.index')"
    class="px-4 py-2 rounded-xl border bg-white hover:bg-slate-50"
  >
    إدارة العروض
  </Link>
  
          <Link
    :href="route('owner.offers.create')"
    class="px-4 py-2 rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
  >
    أضف عرضًا الآن
  </Link>
        </div>
      </div>

      <!-- KPIs -->
      <div class="grid sm:grid-cols-4 gap-4">
        <div class="border rounded-2xl p-5">
          <div class="text-slate-500 text-sm">عدد المساحات</div>
          <div class="text-3xl font-bold">{{ stats?.workspaces_count ?? 0 }}</div>
        </div>
        <div class="border rounded-2xl p-5">
          <div class="text-slate-500 text-sm">إجمالي الحجوزات</div>
          <div class="text-3xl font-bold">{{ stats?.bookings_count ?? 0 }}</div>
        </div>
        <div class="border rounded-2xl p-5">
          <div class="text-slate-500 text-sm">حجوزات معلّقة</div>
          <div class="text-3xl font-bold">{{ stats?.pending_count ?? 0 }}</div>
        </div>
        <div class="border rounded-2xl p-5">
          <div class="text-slate-500 text-sm">عروض فعّالة</div>
          <div class="text-3xl font-bold">{{ stats?.active_offers_count ?? 0 }}</div>
        </div>
      </div>

      <!-- أكثر المساحات خصمًا -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold">أكثر المساحات خصمًا</h2>
          <Link :href="route('owner.workspaces.index')" class="text-indigo-600 hover:underline">
            كل المساحات →
          </Link>
        </div>

        <div
          v-if="!topDiscounted?.length"
          class="border rounded-2xl p-6 text-slate-600"
        >
          لا توجد عروض فعّالة بعد. ابدأ بإضافة أول عرض لزيادة التحويلات.
        </div>

        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div
            v-for="w in topDiscounted"
            :key="w.id"
            class="border rounded-2xl overflow-hidden bg-white"
          >
            <div class="relative">
              <img
                :src="w.image_url || 'https://placehold.co/800x500?text=Workspace'"
                class="w-full h-40 object-cover"
                alt=""
              />
              <div
                v-if="w.max_discount && w.max_discount > 0"
                class="absolute top-2 left-2 bg-rose-600 text-white text-xs font-semibold px-2 py-1 rounded"
              >
                خصم {{ w.max_discount }}%
              </div>
            </div>

            <div class="p-4 space-y-1">
              <div class="font-semibold">{{ w.name }}</div>
              <div class="text-slate-600 text-sm">{{ w.location }}</div>

              <div class="mt-2 text-sm">
                <template v-if="w.max_discount && w.max_discount > 0">
                  <div class="flex items-center gap-2">
                    <span class="line-through text-slate-400">${{ Number(w.price_per_hour ?? 0).toFixed(2) }}/h</span>
                    <span class="font-bold">${{ discountedPrice(w.price_per_hour, w.max_discount) }}/h</span>
                  </div>
                </template>
                <template v-else>
                  <span class="font-bold">${{ Number(w.price_per_hour ?? 0).toFixed(2) }}/h</span>
                </template>
              </div>

              <div class="pt-3 flex gap-2">
                <Link
                  :href="route('owner.workspaces.edit', w.id)"
                  class="px-3 py-1.5 border rounded hover:bg-slate-50 text-sm"
                >
                  تعديل
                </Link>
                <Link
                  :href="route('owner.bookings.index', { search: w.name })"
                  class="px-3 py-1.5 border rounded hover:bg-slate-50 text-sm"
                >
                  حجوزاتها
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
