@extends('layouts.app')
@section('title', 'Certifications')
@section('content')
<div class="card">
    <h1>Certifications</h1>
    <a href="{{ route('admin.certifications.create') }}" class="btn">Issue Certificate</a>
    <table style="margin-top:14px">
        <thead><tr><th>Student</th><th>Course</th><th>Certificate No</th><th>Issued</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($certifications as $c)
            <tr>
                <td>{{ $c->student->user->name }}</td>
                <td>{{ $c->course->name }}</td>
                <td>{{ $c->certificate_no }}</td>
                <td>{{ $c->issued_at }}</td>
                <td><span class="badge @if($c->status==='active') green @else red @endif">{{ $c->status }}</span></td>
                <td>
                    @if($c->status==='active')
                    <form action="{{ route('admin.certifications.revoke', $c) }}" method="POST" style="display:inline">@csrf<button class="btn sm red">Revoke</button></form>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="6">No certificates.</td></tr> @endforelse
        </tbody>
    </table>
    {{ $certifications->links() }}
</div>
@endsection
