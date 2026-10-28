@extends('layouts.app')
@section('title', 'Submit Feedback')
@section('content')
<div class="card" style="max-width:680px">
    <h1>Feedback — {{ $student->user->name }}</h1>
    <p>{{ $schedule->course->name }} · {{ $schedule->date }} {{ $schedule->time_start }}</p>
    @if($feedback)
        <div class="alert ok">Existing feedback found. Submitting will update it.</div>
    @endif
    <form method="POST" action="{{ route('instructor.feedback.store', [$schedule, $student]) }}">
        @csrf
        <div class="form-group">
            <label>Feedback / Observations</label>
            <textarea name="raw_text" required>{{ old('raw_text', $feedback->raw_text ?? '') }}</textarea>
        </div>
        @if($schedule->course->type === 'PDC')
            <div class="grid">
                @foreach(['steering'=>'Steering','brake'=>'Braking','mirror'=>'Side Mirror &amp; Observation'] as $k=>$lbl)
                    <div class="form-group">
                        <label>{!! $lbl !!} (1-5)</label>
                        <input type="number" name="star_{{ $k }}" min="1" max="5" value="{{ old('star_'.$k, $feedback->{'star_'.$k} ?? '') }}">
                    </div>
                @endforeach
            </div>
        @else
            <p class="muted">TDC is exam-only — use the exam score field in the assessment screen.</p>
        @endif
        <button class="btn" type="submit">Save Feedback</button>
    </form>
</div>
@endsection
