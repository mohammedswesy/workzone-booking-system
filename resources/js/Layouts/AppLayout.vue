<template>
  <div class="flex h-screen bg-gray-100">
    <!-- Sidebar -->
    <aside class="w-64 bg-gray-900 text-white flex flex-col">
      <div class="p-4 text-2xl font-bold border-b border-gray-700">
        WorkZone
      </div>

      <nav class="flex-1 p-4 space-y-1">
        <template v-for="item in currentMenu" :key="item.href">
          <Link
            :href="item.href"
            :method="item.method || 'get'"
            :as="item.as || 'a'"
            class="block px-3 py-2 rounded hover:bg-gray-700"
          >
            {{ item.label }}
          </Link>
        </template>
      </nav>

      <div class="p-4 border-t border-gray-700">
        <Link href="/logout" method="post" as="button" class="w-full bg-red-600 hover:bg-red-700 py-2 rounded">
          تسجيل خروج
        </Link>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col">
      <header class="bg-white shadow px-6 py-4 flex justify-between items-center">
        <h1 class="text-xl font-bold">لوحة WorkZone</h1>
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-600">الدور: {{ roleLabel }}</span>
          <span class="text-sm text-gray-600">👤 {{ user?.name || 'Guest' }}</span>
        </div>
      </header>

      <main class="flex-1 p-6 overflow-y-auto">
        <slot />
      </main>

      <footer class="bg-gray-200 text-center py-3">
        <p>© 2025 WorkZone. جميع الحقوق محفوظة.</p>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const user = computed(() => page.props.auth?.user || null)
const role = computed(() => (page.props.auth?.role || 'guest').toLowerCase())

const MENUS = {
  guest: [
    { label: 'الرئيسية', href: '/' },
    { label: 'المساحات', href: '/spaces' },
  ],
  user: [
    { label: 'الرئيسية', href: '/' },
    { label: 'المساحات', href: '/spaces' },
    { label: 'حجوزاتي', href: '/user/bookings' },
  ],
  owner: [
    { label: 'لوحة المالك', href: '/owner/dashboard' },
    { label: 'مساحاتي', href: '/owner/workspaces' },
    { label: 'الحجوزات', href: '/owner/bookings' },
    { label: 'العروض', href: '/owner/offers' },
  ],
  admin: [
    { label: 'لوحة التحكم', href: '/admin/dashboard' },
    { label: 'المستخدمون', href: '/admin/users' },
    { label: 'المساحات', href: '/admin/workspaces' },
    { label: 'الحجوزات', href: '/admin/bookings' },
    { label: 'التقارير', href: '/admin/reports' },
  ],
}

const currentMenu = computed(() => MENUS[role.value] || MENUS.guest)

const roleNamesMap = { guest: 'زائر', user: 'مستخدم', owner: 'مالك', admin: 'أدمن' }
const roleLabel = computed(() => roleNamesMap[role.value] || role.value)
</script>
