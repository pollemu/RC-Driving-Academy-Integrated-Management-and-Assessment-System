@extends('layouts.app')
@section('title', 'Instructors')
@section('content')
<div class="card">
    <h1>Instructors</h1>
    <a href="{{ route('admin.instructors.create') }}" class="btn">Add Instructor</a>
    <table style="margin-top:14px">
        <thead><tr><th>Name</th><th>Email</th><th>Specialization</th><th>License No</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($instructors as $i)
            <tr>
                <td>{{ $i->user->name }}</td>
                <td>{{ $i->user->email }}</td>
                <td>{{ $i->specialization }}</td>
                <td>{{ $i->license_no }}</td>
                <td>@if($i->user->is_active)<span class="badge green">Active</span>@else<span class="badge red">Deactivated</span>@endif</td>
                <td>
                    <form action="{{ route('admin.instructors.toggle', $i) }}" method="POST" style="display:inline">@csrf
                        <button class="btn sm gray">{{ $i->user->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </td>
            </tr>
        @empty <tr><td colspan="6">No instructors.</td></tr> @endforelse
        </tbody>
    </table>
    {{ $instructors->links() }}
</div>
@endsection
