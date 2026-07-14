@extends('layouts.app')
@section('title', 'Student Detail')
@section('content')
<div class="card">
    <h1>{{ $student->user->name }}</h1>
    <p>Email: {{ $student->user->email }} · Phone: {{ $student->phone }} · License: {{ $student->license_type ?? '—' }}</p>
    <form action="{{ route('admin.students.toggle', $student) }}" method="POST" style="display:inline">@csrf
        <button class="btn gray">{{ $student->user->is_active ? 'Deactivate' : 'Activate' }}</button>
    </form>
</div>
<div class="card">
    <h3>Enrollments</h3>
    <table><thead><tr><th>Course</th><th>Status</th><th>Enrolled</th></tr></thead><tbody>
    @forelse($student->enrollments as $e)
        <tr><td>{{ $e->course->name }}</td><td>{{ $e->status }}</td><td>{{ $e->enrolled_at }}</td></tr>
    @empty <tr><td colspan="3">None</td></tr> @endforelse
    </tbody></table>
</div>
<div class="card">
    <h3>Payments</h3>
    <table><thead><tr><th>Course</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>
    @forelse($student->payments as $p)
        <tr><td>{{ $p->course->name }}</td><td>₱{{ number_format($p->amount,2) }}</td><td>{{ $p->method }}</td><td>{{ $p->status }}</td></tr>
    @empty <tr><td colspan="4">None</td></tr> @endforelse
    </tbody></table>
</div>
<div class="card">
    <h3>Certificates</h3>
    <table><thead><tr><th>Course</th><th>Certificate No</th><th>Status</th></tr></thead><tbody>
    @forelse($student->certifications as $c)
        <tr><td>{{ $c->course->name }}</td><td>{{ $c->certificate_no }}</td><td>{{ $c->status }}</td></tr>
    @empty <tr><td colspan="3">None</td></tr> @endforelse
    </tbody></table>
</div>
@endsection
