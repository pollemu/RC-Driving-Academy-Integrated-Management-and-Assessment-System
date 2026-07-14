@extends('layouts.app')
@section('title', 'Payment')
@section('content')
<div class="card" style="max-width:560px">
    <h1>Payment — {{ $enrollment->course->name }}</h1>
    <p>Amount due: <strong>₱{{ number_format($enrollment->course->fee,2) }}</strong></p>
    <p class="alert" style="background:#fef3c7;color:#78350f">Accepted methods: <strong>GCash</strong> and <strong>BDO</strong> only.</p>
    <form method="POST" action="{{ route('student.payment.store', $enrollment) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label>Payment Method</label>
            <select name="method" required>
                <option value="gcash">GCash</option>
                <option value="bdo">BDO</option>
            </select>
        </div>
        <div class="form-group">
            <label>Reference No (GCash/BDO transaction no.)</label>
            <input name="reference_no" value="{{ old('reference_no') }}">
        </div>
        <div class="form-group">
            <label>Receipt Screenshot/PDF</label>
            <input type="file" name="receipt" accept="image/*,application/pdf">
        </div>
        <button class="btn" type="submit">Proceed to Payment</button>
    </form>
</div>
@endsection
