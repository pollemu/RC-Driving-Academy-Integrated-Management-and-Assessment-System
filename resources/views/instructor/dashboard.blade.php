@extends('layouts.app')
@section('title', 'Instructor Dashboard')
@section('content')
<h1>Instructor Dashboard</h1>
<div class="card">
    <h3>Today's Sessions</h3>
    @forelse($today as $s)
        <p>• {{ $s->course->name }} — {{ $s->time_start }}–{{ $s->time_end }} @ {{ $s->venue }}
            <a href="{{ route('instructor.attendance.show', $s) }}" class="btn sm">Take Attendance</a></p>
    @empty
        <p>No sessions scheduled today.</p>
    @endforelse
</div>
<div class="card">
    <h3>Upcoming Sessions</h3>
    <table><thead><tr><th>Date</th><th>Time</th><th>Course</th><th>Venue</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($schedules as $s)
        <tr>
            <td>{{ $s->date }}</td><td>{{ $s->time_start }}–{{ $s->time_end }}</td>
            <td>{{ $s->course->name }}</td><td>{{ $s->venue }}</td>
            <td><span class="badge gray">{{ $s->status }}</span></td>
            <td><a href="{{ route('instructor.attendance.show', $s) }}" class="btn sm gray">Attendance</a></td>
        </tr>
    @empty <tr><td colspan="6">No sessions.</td></tr> @endforelse
    </tbody></table>
</div>
@endsection
