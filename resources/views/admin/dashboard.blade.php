@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('content')
<h1>Admin Dashboard</h1>
<div class="grid">
    <div class="stat"><div class="num">{{ $students }}</div><div class="lbl">Students</div></div>
    <div class="stat"><div class="num">{{ $instructors }}</div><div class="lbl">Instructors</div></div>
    <div class="stat"><div class="num">{{ $activeEnrollments }}</div><div class="lbl">Active Enrollments</div></div>
    <div class="stat"><div class="num">{{ $pendingPayments }}</div><div class="lbl">Pending Payments</div></div>
    <div class="stat"><div class="num">{{ $schedules }}</div><div class="lbl">Schedules</div></div>
    <div class="stat"><div class="num">{{ $certifications }}</div><div class="lbl">Certificates</div></div>
</div>
<div class="card">
    <h3>Quick Actions</h3>
    <a href="{{ route('admin.schedules.create') }}" class="btn">Create Schedule</a>
    <a href="{{ route('admin.instructors.create') }}" class="btn gray">Add Instructor</a>
    <a href="{{ route('admin.reports.index') }}" class="btn gray">Reports</a>
</div>
@endsection
