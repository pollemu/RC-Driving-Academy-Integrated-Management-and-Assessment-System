@extends('layouts.app')
@section('title', 'Add Instructor')
@section('content')
<div class="card" style="max-width:560px">
    <h1>Add Instructor</h1>
    <form method="POST" action="{{ route('admin.instructors.store') }}">
        @csrf
        <div class="form-group"><label>Full Name</label><input name="name" value="{{ old('name') }}" required></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required></div>
        <div class="form-group"><label>Phone</label><input name="phone" value="{{ old('phone') }}"></div>
        <div class="form-group"><label>Specialization</label>
            <select name="specialization" required>
                <option value="TDC">TDC</option>
                <option value="PDC">PDC</option>
                <option value="TDC+PDC">TDC + PDC</option>
            </select>
        </div>
        <div class="form-group"><label>License No</label><input name="license_no" value="{{ old('license_no') }}"></div>
        <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn" type="submit">Create Instructor</button>
    </form>
</div>
@endsection
