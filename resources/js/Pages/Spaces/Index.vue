<script setup>
import Navbar from '@/Components/Navbar.vue'
import SpaceCard from '@/Components/SpaceCard.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

// بيانات جاية من لارافيل (حالياً بنعطي ديفولت وهمي)
const props = defineProps({
  spaces: { type: Array, default: () => ([
    { id: 1, name: 'OpenHub', location: 'Gaza City', capacity: 16, price_per_hour: 6, image_url: 'https://images.unsplash.com/photo-1497366216548-37526070297c?q=80&w=400' },
    { id: 2, name: 'TechHub', location: 'Gaza City', capacity: 10, price_per_hour: 5, image_url: 'https://images.unsplash.com/photo-1507209696998-3c532be9b2b3?q=80&w=400' },
  ])},
  search: { type: String, default: '' },
})

const q = ref(props.search)

function search() {
  router.get('/spaces', { search: q.value }, { preserveState: true, replace: true })
}
</script>

<template>
  <Navbar />
  <div class="max-w-6xl mx-auto px-4 py-6 space-y-4">
    <div class="bg-white border rounded-lg p-4 flex gap-3">
      <input v-model="q" placeholder="Search" class="border rounded-md px-3 py-2 flex-1" />
      <button @click="search" class="bg-blue-600 text-white px-4 py-2 rounded-md">Search</button>
    </div>

    <div class="grid gap-3">
      <SpaceCard v-for="s in props.spaces" :key="s.id" :space="s" />
      <p v-if="props.spaces.length === 0" class="text-slate-500">No spaces found.</p>
    </div>
  </div>
</template>
