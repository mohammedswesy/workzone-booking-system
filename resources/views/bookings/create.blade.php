@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold mb-4">إنشاء حجز جديد</h1>

<a href="{{ route('bookings.index') }}" class="text-gray-600 hover:underline mb-4 inline-block">← العودة إلى قائمة الحجوزات</a>

@if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        يرجى تصحيح الأخطاء أدناه.
    </div>
@endif

<form action="{{ route('bookings.store') }}" method="POST" class="max-w-md">
    @csrf

    <label for="workspace_id" class="block mb-2">اختر مكان العمل:</label>
    <select name="workspace_id" id="workspace_id" class="border rounded w-full mb-1" required>
        <option value="">-- اختر --</option>
        @foreach ($workspaces as $workspace)
            <option value="{{ $workspace->id }}" {{ old('workspace_id') == $workspace->id ? 'selected' : '' }}>
                {{ $workspace->name }} - السعر بالساعة: {{ $workspace->price_per_hour }}$
            </option>
        @endforeach
    </select>
    @error('workspace_id')
        <p class="text-red-600 text-sm mb-4">{{ $message }}</p>
    @enderror

    <label for="hours" class="block mb-2">عدد الساعات:</label>
    <input type="number" name="hours" id="hours" min="1" class="border rounded w-full mb-1" value="{{ old('hours') }}" required>
    @error('hours')
        <p class="text-red-600 text-sm mb-4">{{ $message }}</p>
    @enderror

    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">حجز</button>
</form>
@endsection
