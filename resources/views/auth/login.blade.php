@extends('layouts.app')
@section('title', 'Login')
@section('content')
<div class="card" style="max-width:420px;margin:40px auto">
    <h2>Login</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="remember" style="width:auto"> Remember me</label>
        </div>
        <button class="btn" type="submit">Login</button>
        <a href="{{ route('register') }}" class="btn gray" style="margin-left:8px">Register as Student</a>
    </form>
</div>
@endsection
