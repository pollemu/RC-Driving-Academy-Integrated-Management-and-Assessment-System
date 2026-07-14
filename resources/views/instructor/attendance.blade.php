@extends('layouts.app')
@section('title', 'Attendance')
@section('content')
<div class="card">
    <h1>Sessions</h1>
    <table>
        <thead><tr><th>Date</th><th>Course</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($schedules as $s)
            <tr>
                <td>{{ $s->date }} {{ $s->time_start }}</td>
                <td>{{ $s->course->name }}</td>
                <td><span class="badge gray">{{ $s->status }}</span></td>
                <td><a href="{{ route('instructor.attendance.show', $s) }}" class="btn sm">Manage</a></td>
            </tr>
        @empty <tr><td colspan="4">No sessions.</td></tr> @endforelse
        </tbody>
    </table>
    {{ $schedules->links() }}
</div>
@endsection
