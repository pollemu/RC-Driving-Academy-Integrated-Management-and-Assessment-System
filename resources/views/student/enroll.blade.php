@extends('layouts.app')
@section('title', 'Enroll')
@section('content')
<div class="card">
    <h1>Available Courses</h1>
    <table>
        <thead><tr><th>Course</th><th>Type</th><th>Fee</th><th>Action</th></tr></thead>
        <tbody>
        @foreach($courses as $c)
            <tr>
                <td>{{ $c->name }}</td>
                <td>{{ $c->type }}</td>
                <td>₱{{ number_format($c->fee,2) }}</td>
                <td>
                    @if(in_array($c->id, $enrolled))
                        <span class="badge green">Enrolled</span>
                    @else
                        <form action="{{ route('student.enroll', $c) }}" method="POST" style="display:inline">@csrf
                            <button class="btn sm">Enroll & Pay</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
