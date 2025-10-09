<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const user = computed(() => page.props.auth?.user)

function logout() {
  router.post(route('logout'))
}
</script>

<template>
  <nav class="border-b bg-white">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
      <Link href="/" class="font-semibold text-lg">🏢 WorkZone</Link>

      <div class="flex items-center gap-2">
        <Link href="/spaces" class="px-3 py-2 rounded-md text-sm hover:bg-slate-100">Spaces</Link>

        <template v-if="user">
          <!-- رابط داشبورد واحد -->
          <Link href="/dashboard" class="px-3 py-2 rounded-md text-sm hover:bg-slate-100">
            Dashboard
          </Link>

          <!-- يظهر فقط لو الدور User -->
          <button v-if="user.role === 'user'"
                  @click="$inertia.post(route('become.owner'))"
                  class="px-3 py-2 rounded-md text-sm border">
            Become Owner
          </button>

          <Link href="/profile" class="px-3 py-2 rounded-md text-sm hover:bg-slate-100">Profile</Link>
          <button @click="logout" class="px-3 py-2 rounded-md text-sm border">Sign Out</button>
        </template>

        <template v-else>
          <Link href="/login" class="px-3 py-2 rounded-md text-sm hover:bg-slate-100">Sign In</Link>
          <Link href="/register" class="px-3 py-2 rounded-md text-sm hover:bg-slate-100">Sign Up</Link>
        </template>
      </div>
    </div>
  </nav>
</template>
