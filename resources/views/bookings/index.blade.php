@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold mb-4">قائمة الحجوزات</h1>
@if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
@endif
<table class="table-auto w-full border-collapse">
    <thead class="bg-gray-100">
        <tr>
            <th>مكان العمل</th>
            <th>عدد الساعات</th>
            <th>السعر الكلي</th>
            <th>الحالة</th>
            <th>إجراءات</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bookings as $booking)
            <tr>
                <td>{{ $booking->workspace->name }}</td>
                <td>{{ $booking->hours }}</td>
                <td>{{ $booking->total_price }}</td>
                <td>
                    @if($booking->status === 'pending') قيد الانتظار
                    @elseif($booking->status === 'confirmed') مؤكد
                    @elseif($booking->status === 'cancelled') ملغي
                    @endif
                </td>
                <td>
                    <a href="{{ route('bookings.edit', $booking) }}">تعديل</a>
                    <form action="{{ route('bookings.destroy', $booking) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">لا توجد حجوزات.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
