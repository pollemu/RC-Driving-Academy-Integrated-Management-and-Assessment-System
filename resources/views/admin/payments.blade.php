@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="card">
    <h1>Payments</h1>
    <div style="margin-bottom:12px">
        @foreach(['pending','verified','rejected'] as $st)
            <a href="{{ route('admin.payments.index', ['status'=>$st]) }}" class="btn sm @if($status===$st) green @else gray @endif">{{ ucfirst($st) }}</a>
        @endforeach
    </div>
    <table>
        <thead><tr><th>Student</th><th>Course</th><th>Amount</th><th>Method</th><th>Reference</th><th>Receipt</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->student->user->name }}</td>
                <td>{{ $p->course->name }}</td>
                <td>₱{{ number_format($p->amount,2) }}</td>
                <td>{{ $p->method }}</td>
                <td>{{ $p->reference_no }}</td>
                <td>@if($p->receipt_path)<a href="{{ asset('storage/'.$p->receipt_path) }}" target="_blank">View</a>@else—@endif</td>
                <td><span class="badge gray">{{ $p->status }}</span></td>
                <td>
                    @if($p->status !== 'verified')
                        <form action="{{ route('admin.payments.verify', $p) }}" method="POST" style="display:inline">@csrf<button class="btn sm green">Verify</button></form>
                    @endif
                    @if($p->status !== 'rejected')
                        <form action="{{ route('admin.payments.reject', $p) }}" method="POST" style="display:inline">@csrf<button class="btn sm red">Reject</button></form>
                    @endif
                </td>
            </tr>
        @empty <tr><td colspan="8">No payments.</td></tr> @endforelse
        </tbody>
    </table>
    {{ $payments->links() }}
</div>
@endsection
