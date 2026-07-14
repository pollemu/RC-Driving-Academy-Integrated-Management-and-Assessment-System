@extends('layouts.app')
@section('title', 'Create Schedule')
@section('content')
<div class="card" style="max-width:620px">
    <h1>Create Schedule</h1>
    <form method="POST" action="{{ route('admin.schedules.store') }}">
        @csrf
        <div class="form-group"><label>Course</label>
            <select name="course_id" required>
                @foreach($courses as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="form-group"><label>Instructor</label>
            <select name="instructor_id" required>
                @foreach($instructors as $i)<option value="{{ $i->id }}">{{ $i->user->name }} ({{ $i->specialization }})</option>@endforeach
            </select>
        </div>
        <div class="form-group"><label>Date</label><input type="date" name="date" required></div>
        <div class="form-group"><label>Start Time</label><input type="time" name="time_start" required></div>
        <div class="form-group"><label>End Time</label><input type="time" name="time_end" required></div>
        <div class="form-group"><label>Venue</label><input name="venue"></div>
        <div class="form-group"><label>Max Students</label><input type="number" name="max_students" min="1" value="1" required></div>
        <button class="btn" type="submit">Create</button>
    </form>
</div>
@endsection
