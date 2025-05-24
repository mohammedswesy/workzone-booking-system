@extends('layouts.app')

@section('content')
    <h1>حجوزاتي</h1>

    @if($bookings->isEmpty())
        <p>لا توجد حجوزات حتى الآن.</p>
    @else
        <ul>
            @foreach ($bookings as $booking)
                <li>
                    <h3>{{ $booking->workspace->name }}</h3>
                    <p>الموقع: {{ $booking->workspace->location }}</p>
                    <p>عدد الساعات: {{ $booking->hours }}</p>
                    <p>الإجمالي: ${{ $booking->total_price }}</p>
                    <p>الحالة: {{ ucfirst($booking->status) }}</p>
                    <small>تاريخ الحجز: {{ $booking->created_at->format('Y-m-d H:i') }}</small>
                </li>
            @endforeach
        </ul>
    @endif
@endsection