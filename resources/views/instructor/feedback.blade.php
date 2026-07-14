@extends('layouts.app')
@section('title', 'Feedback')
@section('content')
<div class="card">
    <h1>PDC Feedback Submission</h1>
    <p>Select a student below to submit session feedback. Only PDC sessions are assessed by the NLP service.</p>
    @forelse($schedules as $s)
        <h3 style="margin-top:18px">{{ $s->date }} · {{ $s->course->name }} @if($s->course->type !== 'PDC')<span class="badge gray">TDC – no NLP</span>@endif</h3>
        <ul>
        @forelse($s->studentSchedules as $b)
            <li>
                {{ $b->student->user->name }}
                @if($s->course->type === 'PDC')
                    <a href="{{ route('instructor.feedback.create', [$s, $b->student]) }}" class="btn sm gray">Submit Feedback</a>
                @endif
            </li>
        @empty
            <li>No students booked.</li>
        @endforelse
        </ul>
    @empty
        <p>No sessions available.</p>
    @endforelse
</div>
@endsection
