@extends('layouts.app')
@section('title', 'Student Dashboard')
@section('content')
<h1>Welcome, {{ $student->user->name }}</h1>
<div class="card">
    <h3>My Enrollments</h3>
    <table>
        <thead><tr><th>Course</th><th>Status</th><th>Enrolled</th></tr></thead>
        <tbody>
        @forelse($student->enrollments as $e)
            <tr>
                <td>{{ $e->course->name }}</td>
                <td>
                    @if($e->status==='active')<span class="badge green">Active</span>
                    @elseif($e->status==='pending')<span class="badge amber">Pending Payment</span>
                    @elseif($e->status==='completed')<span class="badge gray">Completed</span>
                    @else<span class="badge red">Cancelled</span>@endif
                </td>
                <td>{{ $e->enrolled_at }}</td>
            </tr>
        @empty <tr><td colspan="3">No enrollments yet. <a href="{{ route('student.enroll.index') }}">Enroll now</a>.</td></tr> @endforelse
        </tbody>
    </table>
</div>
<div class="card">
    <h3>My Booked Sessions</h3>
    <table>
        <thead><tr><th>Course</th><th>Date</th><th>Time</th><th>Instructor</th></tr></thead>
        <tbody>
        @forelse($bookings as $b)
            <tr>
                <td>{{ $b->schedule->course->name }}</td>
                <td>{{ $b->schedule->date }}</td>
                <td>{{ $b->schedule->time_start }}–{{ $b->schedule->time_end }}</td>
                <td>{{ $b->schedule->instructor->user->name }}</td>
            </tr>
        @empty <tr><td colspan="4">No sessions booked.</td></tr> @endforelse
        </tbody>
    </table>
</div>
@endsection
