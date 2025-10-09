<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  users: Object
})
</script>

<template>
  <AppLayout title="Users">
    <div class="bg-white border rounded">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left">#</th>
            <th class="px-3 py-2 text-left">Name</th>
            <th class="px-3 py-2 text-left">Email</th>
            <th class="px-3 py-2 text-left">Role</th>
            <th class="px-3 py-2 text-left">Joined</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users.data" :key="u.id" class="border-t">
            <td class="px-3 py-2">{{ u.id }}</td>
            <td class="px-3 py-2">{{ u.name }}</td>
            <td class="px-3 py-2">{{ u.email }}</td>
            <td class="px-3 py-2">{{ u.role }}</td>
            <td class="px-3 py-2">{{ u.created_at }}</td>
          </tr>
          <tr v-if="!users.data?.length">
            <td colspan="5" class="px-3 py-6 text-center text-gray-500">No users.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :links="users.links" />
  </AppLayout>
</template>
