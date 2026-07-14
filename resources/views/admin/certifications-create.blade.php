@extends('layouts.app')
@section('title', 'Issue Certificate')
@section('content')
<div class="card" style="max-width:560px">
    <h1>Issue Certificate</h1>
    <form method="POST" action="{{ route('admin.certifications.store') }}">
        @csrf
        <div class="form-group"><label>Student</label>
            <select name="student_id" id="student" required>
                <option value="">Select student…</option>
                @foreach($students as $s)<option value="{{ $s->id }}">{{ $s->user->name }}</option>@endforeach
            </select>
        </div>
        <div class="form-group"><label>Course</label>
            <select name="course_id" id="course" required><option value="">Select student first</option></select>
        </div>
        <button class="btn" type="submit">Issue</button>
    </form>
</div>
<script>
document.getElementById('student').addEventListener('change', function() {
    const sid = this.value;
    const course = document.getElementById('course');
    course.innerHTML = '<option>Loading…</option>';
    if (!sid) return;
    fetch('{{ url('admin/certifications/courses') }}/' + sid)
        .then(r => r.json())
        .then(data => {
            course.innerHTML = '';
            data.forEach(c => {
                const o = document.createElement('option');
                o.value = c.course_id;
                o.textContent = c.name + (c.eligible ? '' : ' (already certified)');
                if (!c.eligible) o.disabled = true;
                course.appendChild(o);
            });
        });
});
</script>
@endsection
