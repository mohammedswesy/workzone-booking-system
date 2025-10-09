<script setup>
const props = defineProps({
  links: { type: Array, required: true },
})
</script>

<template>
  <nav v-if="links && links.length" class="mt-6 flex flex-wrap gap-2">
    <a v-for="(link, i) in links" :key="i"
       :href="link.url || '#'"
       v-html="link.label"
       class="px-3 py-1.5 rounded border text-sm"
       :class="[
         link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white hover:bg-gray-50',
         !link.url ? 'opacity-50 pointer-events-none' : ''
       ]" />
  </nav>
</template>
