@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-semibold mb-6">تفاصيل مكان العمل</h1>

    <div class="bg-white dark:bg-gray-800 shadow rounded p-6 space-y-4">
        <div>
            <h2 class="font-bold text-lg">الاسم:</h2>
            <p>{{ $workspace->name }}</p>
        </div>

        <div>
            <h2 class="font-bold text-lg">الموقع:</h2>
            <p>{{ $workspace->location ?? '-' }}</p>
        </div>

        <div>
            <h2 class="font-bold text-lg">السعة:</h2>
            <p>{{ $workspace->capacity ?? '-' }}</p>
        </div>

        <div class="pt-4">
            <a href="{{ route('workspaces.index') }}" class="text-blue-600 hover:underline">العودة لقائمة أماكن العمل</a>
        </div>
    </div>
</div>
@endsection
