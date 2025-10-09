<script setup>
import Navbar from '@/Components/Navbar.vue'
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <Navbar />
  <div class="max-w-6xl mx-auto px-4 py-8 grid md:grid-cols-2 items-center gap-8">
    <div>
      <p class="text-slate-500 mb-2">Gaza Tashreel</p>
      <h1 class="text-4xl md:text-5xl font-bold leading-tight">
        Find Your <br /> Coworking Space
      </h1>
      <p class="text-slate-600 mt-3 mb-6">Search and book desks, rooms, and shared spaces in minutes.</p>
      <Link href="/spaces" class="inline-block bg-blue-600 text-white px-5 py-3 rounded-md">Explore Spaces</Link>
    </div>
    <div class="rounded-xl overflow-hidden border">
      <img src="https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=1200&auto=format&fit=crop" alt="workspace" class="w-full h-full object-cover" />
    </div>
  </div>
</template>
