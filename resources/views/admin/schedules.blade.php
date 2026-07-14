@extends('layouts.app')
@section('title', 'Schedules')
@section('content')
<div class="card">
    <h1>Schedules</h1>
    <a href="{{ route('admin.schedules.create') }}" class="btn">Create Schedule</a>
    <table style="margin-top:14px">
        <thead><tr><th>Date</th><th>Time</th><th>Course</th><th>Instructor</th><th>Venue</th><th>Booked/Max</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($schedules as $s)
            <tr>
                <td>{{ $s->date }}</td>
                <td>{{ $s->time_start }}–{{ $s->time_end }}</td>
                <td>{{ $s->course->name }}</td>
                <td>{{ $s->instructor->user->name }}</td>
                <td>{{ $s->venue }}</td>
                <td>{{ $s->studentSchedules->count() }}/{{ $s->max_students }}</td>
                <td><span class="badge gray">{{ $s->status }}</span></td>
                <td>
                    @if($s->status !== 'cancelled')
                    <form action="{{ route('admin.schedules.cancel', $s) }}" method="POST" style="display:inline">@csrf
                        <button class="btn sm red">Cancel</button>
                    </form>@endif
                </td>
            </tr>
        @empty <tr><td colspan="8">No schedules.</td></tr> @endforelse
        </tbody>
    </table>
    {{ $schedules->links() }}
</div>
@endsection
