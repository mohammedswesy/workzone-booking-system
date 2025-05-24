@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">إضافة مكان عمل جديد</h1>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('workspaces.store') }}" method="POST" class="space-y-6">
        @csrf

        <h1>Test Content</h1>

        <div>
            <label for="name" class="block font-medium text-gray-700 dark:text-gray-300">اسم المكان <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>

        <div>
            <label for="location" class="block font-medium text-gray-700 dark:text-gray-300">الموقع</label>
            <input type="text" name="location" id="location" value="{{ old('location') }}"
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
        </div>

        <div>
            <label for="capacity" class="block font-medium text-gray-700 dark:text-gray-300">السعة</label>
            <input type="number" name="capacity" id="capacity" value="{{ old('capacity') }}"
                class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" min="1">
        </div>
        <div>
    <label for="price_per_hour" class="block font-medium text-gray-700 dark:text-gray-300">السعر بالساعة <span class="text-red-500">*</span></label>
    <input type="number" name="price_per_hour" id="price_per_hour" value="{{ old('price_per_hour') }}" required
           class="mt-1 block w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white" min="0" step="0.01">
</div>


        <div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">حفظ</button>
            <a href="{{ route('workspaces.index') }}" class="ml-4 text-gray-700 hover:underline">إلغاء</a>
        </div>
    </form>
</div>




@endsection
