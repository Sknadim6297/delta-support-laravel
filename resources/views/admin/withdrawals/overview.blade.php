@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Withdrawal Overview</h1>
        <p>Counts come from stored withdrawal requests. Review, holds, and payouts are not available.</p>
    </header>
    <div>
        <article><p>Total requests</p><p>{{ $total }}</p></article>
        @foreach ($counts as $status => $count)
            <article>
                <p>{{ \App\Support\WithdrawalStatus::label($status) }}</p>
                <p>{{ $count }}</p>
            </article>
        @endforeach
    </div>
</div>
@endsection
