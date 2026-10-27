@extends('member.layouts.app')
@section('title', $kind === 'roi' ? 'My ROI History' : 'My Level Income History')
@section('content')
<div>
    <header>
        <h1>{{ $kind === 'roi' ? 'My ROI History' : 'My Level Income History' }}</h1>
        <p>Only your stored investment records are listed, shown as USDT. They are not package purchase credits, and new package income is not being calculated.</p>
    </header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference" aria-label="Search your records">
        <button>Search</button>
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'member'])
</div>
@endsection
