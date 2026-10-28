@extends('layouts.app')
@section('title', 'Progress')
@section('content')
<h1>My Progress</h1>
<div class="card">
    <h3>Enrollments</h3>
    <table><thead><tr><th>Course</th><th>Status</th></tr></thead><tbody>
    @forelse($enrollments as $e)<tr><td>{{ $e->course->name }}</td><td>{{ $e->status }}</td></tr>@empty<tr><td colspan="2">None</td></tr>@endforelse
    </tbody></table>
</div>
<div class="card">
    <h3>Booked Sessions</h3>
    <table><thead><tr><th>Course</th><th>Date</th><th>Time</th></tr></thead><tbody>
    @forelse($bookings as $b)<tr><td>{{ $b->schedule->course->name }}</td><td>{{ $b->schedule->date }}</td><td>{{ $b->schedule->time_start }}</td></tr>@empty<tr><td colspan="3">None</td></tr>@endforelse
    </tbody></table>
</div>
<div class="card">
    <h3>Assessments</h3>
    <table><thead><tr><th>Course</th><th>Classification</th><th>Score</th><th>Result</th><th>Confidence</th><th>Recommendation</th></tr></thead><tbody>
    @forelse($assessments as $a)
        <tr>
            <td>{{ $a->feedback->schedule->course->name ?? '—' }}</td>
            <td>
                @if($a->classification==='fast')<span class="badge green">Fast Learner</span>
                @elseif($a->classification==='average')<span class="badge amber">Average Learner</span>
                @else<span class="badge red">Needs Improvement</span>@endif
            </td>
            <td>{{ $a->score !== null ? number_format($a->score, 0).'%' : '—' }}</td>
            <td>{{ $a->passed === null ? '—' : ($a->passed ? 'Passed' : 'Not yet passed') }}</td>
            <td>{{ $a->confidence_score ? number_format($a->confidence_score * 100, 0).'%' : '—' }}</td>
            <td>{{ $a->recommendation }}</td>
        </tr>
    @empty<tr><td colspan="6">No assessments yet.</td></tr>@endforelse
    </tbody></table>
</div>
<div class="card">
    <h3>Certificates</h3>
    <table><thead><tr><th>Course</th><th>Certificate No</th><th>Status</th></tr></thead><tbody>
    @forelse($certifications as $c)<tr><td>{{ $c->course->name }}</td><td>{{ $c->certificate_no }}</td><td>{{ $c->status }}</td></tr>@empty<tr><td colspan="3">None yet.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
