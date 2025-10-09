<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'user', // user | owner | admin
})

function submit() {
  form.post(route('admin.users.store'))
}
</script>

<template>
  <AppLayout title="إضافة مستخدم">
    <div class="max-w-xl mx-auto px-4 py-6">
      <h1 class="text-xl font-semibold mb-4">إضافة مستخدم</h1>
      <form @submit.prevent="submit" class="bg-white border rounded p-4 space-y-3">
        <input v-model="form.name" class="border rounded px-3 py-2 w-full" placeholder="الاسم" />
        <div class="text-red-600 text-sm" v-if="form.errors.name">{{ form.errors.name }}</div>

        <input v-model="form.email" type="email" class="border rounded px-3 py-2 w-full" placeholder="الإيميل" />
        <div class="text-red-600 text-sm" v-if="form.errors.email">{{ form.errors.email }}</div>

        <select v-model="form.role" class="border rounded px-3 py-2 w-full">
          <option value="user">User</option>
          <option value="owner">Owner</option>
          <option value="admin">Admin</option>
        </select>

        <input v-model="form.password" type="password" class="border rounded px-3 py-2 w-full" placeholder="كلمة السر" />
        <input v-model="form.password_confirmation" type="password" class="border rounded px-3 py-2 w-full" placeholder="تأكيد كلمة السر" />

        <div class="flex items-center gap-3">
          <button class="bg-gray-900 text-white px-4 py-2 rounded" :disabled="form.processing">حفظ</button>
          <Link :href="route('admin.users.index')" class="text-slate-600">رجوع</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
