@extends('layouts.app')
@section('title', 'Reports')
@section('content')
<h1>Reports</h1>
<div class="grid">
    <div class="stat"><div class="num">{{ $totalStudents }}</div><div class="lbl">Total Students</div></div>
    <div class="stat"><div class="num">{{ $totalEnrollments }}</div><div class="lbl">Total Enrollments</div></div>
    <div class="stat"><div class="num">{{ $verifiedPayments }}</div><div class="lbl">Verified Payments</div></div>
    <div class="stat"><div class="num">₱{{ number_format($totalRevenue,2) }}</div><div class="lbl">Total Revenue</div></div>
    <div class="stat"><div class="num">{{ $completedSessions }}</div><div class="lbl">Completed Sessions</div></div>
</div>
<div class="card">
    <a href="{{ route('admin.reports.export.students') }}" class="btn">Export Students CSV</a>
</div>
@endsection
