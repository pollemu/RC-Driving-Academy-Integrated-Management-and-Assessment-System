@extends('layouts.app')
@section('title', 'Sessions')
@section('content')
<div class="card">
    <h1>Book a Session</h1>
    <p>Only visible for courses with verified payment. Bookings are limited to available slots.</p>
    <table>
        <thead><tr><th>Course</th><th>Date</th><th>Time</th><th>Instructor</th><th>Venue</th><th>Slots</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($schedules as $s)
            <tr>
                <td>{{ $s->course->name }}</td>
                <td>{{ $s->date }}</td>
                <td>{{ $s->time_start }}–{{ $s->time_end }}</td>
                <td>{{ $s->instructor->user->name }}</td>
                <td>{{ $s->venue }}</td>
                <td>{{ $s->studentSchedules->count() }}/{{ $s->max_students }}</td>
                <td>
                    @if(in_array($s->id, $bookedIds))
                        <span class="badge green">Booked</span>
                    @else
                        <form action="{{ route('student.schedule.book', $s) }}" method="POST" style="display:inline">@csrf
                            <button class="btn sm">Book</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7">No open sessions for your enrolled courses. Make sure payment is verified.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $schedules->links() }}
</div>
@endsection
