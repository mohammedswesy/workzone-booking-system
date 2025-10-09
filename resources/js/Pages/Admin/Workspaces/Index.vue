<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  spaces: Object, // paginate with owner eager-loaded
  filters: Object
})
</script>

<template>
  <AppLayout title="All Workspaces">
    <form method="get" class="mb-4">
      <input name="search" :value="filters?.search" placeholder="Search name/location"
             class="border rounded px-3 py-2 w-72" />
      <button class="ml-2 px-3 py-2 rounded bg-gray-900 text-white">Search</button>
    </form>

    <div class="bg-white border rounded">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">Name</th>
            <th class="px-3 py-2 text-left">Owner</th>
            <th class="px-3 py-2 text-left">Location</th>
            <th class="px-3 py-2 text-left">Capacity</th>
            <th class="px-3 py-2 text-left">Price/h</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in spaces.data" :key="s.id" class="border-t">
            <td class="px-3 py-2">{{ s.id }}</td>
            <td class="px-3 py-2">{{ s.name }}</td>
            <td class="px-3 py-2">{{ s.owner?.name }}</td>
            <td class="px-3 py-2">{{ s.location }}</td>
            <td class="px-3 py-2">{{ s.capacity }}</td>
            <td class="px-3 py-2">{{ s.price_per_hour }}</td>
            <td class="px-3 py-2 text-right">
              <Link :href="route('admin.workspaces.edit', s.id)" class="text-indigo-600 hover:underline">Edit</Link>
            </td>
          </tr>
          <tr v-if="!spaces.data?.length">
            <td colspan="7" class="px-3 py-6 text-center text-gray-500">No workspaces.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :links="spaces.links" />
  </AppLayout>
</template>
