@extends('layouts.app')
@section('title', 'Take Attendance')
@section('content')
<div class="card">
    <h1>Attendance — {{ $schedule->course->name }}</h1>
    <p>{{ $schedule->date }} · {{ $schedule->time_start }}–{{ $schedule->time_end }} · {{ $schedule->venue }}</p>
    <form method="POST" action="{{ route('instructor.attendance.store', $schedule) }}">
        @csrf
        <table>
            <thead><tr><th>Student</th><th>Status</th><th>Notes</th><th>Feedback</th></tr></thead>
            <tbody>
            @forelse($bookings as $b)
                @php $st = $records[$b->student_id] ?? 'absent'; @endphp
                <tr>
                    <td>{{ $b->student->user->name }}</td>
                    <td>
                        <select name="status[{{ $b->student_id }}]">
                            <option value="present" @if($st==='present')selected @endif>Present</option>
                            <option value="absent" @if($st==='absent')selected @endif>Absent</option>
                            <option value="late" @if($st==='late')selected @endif>Late</option>
                        </select>
                    </td>
                    <td><input name="notes[{{ $b->student_id }}]" value=""></td>
                    <td>
                        @if($schedule->course->type === 'PDC')
                            <a href="{{ route('instructor.feedback.create', [$schedule, $b->student]) }}" class="btn sm gray">Feedback</a>
                        @else — @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No students booked for this session.</td></tr>
            @endforelse
            </tbody>
        </table>
        <label style="margin-top:14px"><input type="checkbox" name="mark_done" style="width:auto"> Mark session as done</label>
        <div style="margin-top:14px"><button class="btn" type="submit">Save Attendance</button></div>
    </form>
</div>
@endsection
