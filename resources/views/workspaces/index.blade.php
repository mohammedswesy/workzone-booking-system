@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">أماكن العمل</h1>

    <a href="{{ route('workspaces.create') }}" class="mb-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">إضافة مكان عمل جديد</a>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    <table class="min-w-full bg-white dark:bg-gray-800 shadow rounded">
        <thead>
            <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                <th class="px-6 py-3">الاسم</th>
                <th class="px-6 py-3">الموقع</th>
                <th class="px-6 py-3">السعة</th>
                <th class="px-6 py-3">الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workspaces as $workspace)
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-6 py-4">{{ $workspace->name }}</td>
                    <td class="px-6 py-4">{{ $workspace->location ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $workspace->capacity ?? '-' }}</td>
                    <td class="px-6 py-4 space-x-2 rtl:space-x-reverse">
                        <a href="{{ route('workspaces.show', $workspace->id) }}" class="text-blue-600 hover:underline">عرض</a>
                        <a href="{{ route('workspaces.edit', $workspace->id) }}" class="text-yellow-500 hover:underline">تعديل</a>          
                        <a href="{{ route('bookings.index') }}">حجوزاتي</a>
                        <form action="{{ route('workspaces.destroy', $workspace->id) }}" method="POST" class="inline-block" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
