@extends('layouts.app')
@section('title', 'Students')
@section('content')
<div class="card">
    <h1>Students</h1>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>License</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($students as $s)
            <tr>
                <td>{{ $s->user->name }}</td>
                <td>{{ $s->user->email }}</td>
                <td>{{ $s->phone }}</td>
                <td>{{ $s->license_type ?? '—' }}</td>
                <td>
                    @if($s->user->is_active)<span class="badge green">Active</span>@else<span class="badge red">Deactivated</span>@endif
                </td>
                <td>
                    <a href="{{ route('admin.students.show', $s) }}" class="btn sm">View</a>
                    <form action="{{ route('admin.students.toggle', $s) }}" method="POST" style="display:inline">@csrf
                        <button class="btn sm gray">{{ $s->user->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No students yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $students->links() }}
</div>
@endsection
