@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">تعديل مكان العمل</h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('workspaces.update', $workspace->id) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block font-medium text-gray-700 dark:text-gray-300">اسم المكان <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $workspace->name) }}" required
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>

        <div>
            <label for="location" class="block font-medium text-gray-700 dark:text-gray-300">الموقع</label>
            <input type="text" name="location" id="location" value="{{ old('location', $workspace->location) }}"
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>

        <div>
            <label for="capacity" class="block font-medium text-gray-700 dark:text-gray-300">السعة</label>
            <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $workspace->capacity) }}"
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" min="1">
        </div>

        <div>
            <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">تحديث</button>
            <a href="{{ route('workspaces.index') }}" class="ml-4 text-gray-700 hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection
