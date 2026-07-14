<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RC Driving Academy')</title>
    <style>
        :root { --blue:#1d4ed8; --dark:#0f172a; --gray:#64748b; --bg:#f1f5f9; --green:#16a34a; --red:#dc2626; --amber:#d97706; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:var(--bg); color:var(--dark); }
        header.topbar { background:var(--dark); color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; }
        header.topbar a { color:#fff; text-decoration:none; margin-left:16px; font-size:14px; }
        header.topbar .brand { font-weight:700; font-size:18px; }
        .container { max-width:1100px; margin:24px auto; padding:0 20px; }
        .card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:20px; }
        h1,h2,h3 { margin-top:0; }
        table { width:100%; border-collapse:collapse; }
        th,td { text-align:left; padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:14px; }
        th { background:#f8fafc; color:var(--gray); font-weight:600; }
        .btn { display:inline-block; padding:8px 14px; border-radius:8px; border:none; background:var(--blue); color:#fff; cursor:pointer; text-decoration:none; font-size:14px; }
        .btn:hover { opacity:.9; }
        .btn.gray { background:var(--gray); }
        .btn.green { background:var(--green); }
        .btn.red { background:var(--red); }
        .btn.sm { padding:5px 10px; font-size:13px; }
        .badge { display:inline-block; padding:3px 9px; border-radius:999px; font-size:12px; font-weight:600; }
        .badge.green { background:#dcfce7; color:var(--green); }
        .badge.amber { background:#fef3c7; color:var(--amber); }
        .badge.red { background:#fee2e2; color:var(--red); }
        .badge.gray { background:#e2e8f0; color:var(--gray); }
        .alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
        .alert.ok { background:#dcfce7; color:#14532d; }
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:13px; font-weight:600; margin-bottom:5px; color:var(--gray); }
        input,select,textarea { width:100%; padding:9px 11px; border:1px solid #cbd5e1; border-radius:8px; font-size:14px; }
        textarea { min-height:110px; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; }
        .stat { background:#fff; border-radius:10px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .stat .num { font-size:26px; font-weight:700; }
        .stat .lbl { color:var(--gray); font-size:13px; }
        .nav-links a { color:#cbd5e1; }
        .nav-links a:hover { color:#fff; }
    </style>
</head>
<body>
<header class="topbar">
    <span class="brand">RC Driving Academy · IMAS</span>
    @auth
        <span class="nav-links">
            @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.students.index') }}">Students</a>
                <a href="{{ route('admin.instructors.index') }}">Instructors</a>
                <a href="{{ route('admin.schedules.index') }}">Schedules</a>
                <a href="{{ route('admin.payments.index') }}">Payments</a>
                <a href="{{ route('admin.certifications.index') }}">Certificates</a>
                <a href="{{ route('admin.reports.index') }}">Reports</a>
            @elseif(auth()->user()->isInstructor())
                <a href="{{ route('instructor.dashboard') }}">Dashboard</a>
                <a href="{{ route('instructor.attendance.index') }}">Attendance</a>
                <a href="{{ route('instructor.feedback.index') }}">Feedback</a>
            @else
                <a href="{{ route('student.dashboard') }}">Dashboard</a>
                <a href="{{ route('student.enroll.index') }}">Enroll</a>
                <a href="{{ route('student.schedule.index') }}">Sessions</a>
                <a href="{{ route('student.progress') }}">Progress</a>
            @endif
            <a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('logout').submit();">Logout ({{ auth()->user()->name }})</a>
            <form id="logout" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
        </span>
    @endauth
</header>
<div class="container">
    @if(session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert" style="background:#fee2e2;color:#7f1d1d">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</div>
</body>
</html>
