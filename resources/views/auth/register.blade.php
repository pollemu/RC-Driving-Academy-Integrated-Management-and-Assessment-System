@extends('layouts.app')
@section('title', 'Register')
@section('content')
<div class="card" style="max-width:520px;margin:40px auto">
    <h2>Student Registration</h2>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="{{ old('phone') }}">
        </div>
        <div class="form-group">
            <label>Address</label>
            <input type="text" name="address" value="{{ old('address') }}">
        </div>
        <div class="form-group">
            <label>Birthdate</label>
            <input type="date" name="birthdate" value="{{ old('birthdate') }}">
        </div>
        <div class="form-group">
            <label>License Type</label>
            <select name="license_type">
                <option value="">—</option>
                <option value="non-pro">Non-Professional</option>
                <option value="pro">Professional</option>
                <option value="student-permit">Student Permit</option>
            </select>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="password_confirmation" required>
        </div>
        <button class="btn" type="submit">Register</button>
        <a href="{{ route('login') }}" class="btn gray" style="margin-left:8px">Back to Login</a>
    </form>
</div>
@endsection
