@extends('member.layouts.app')
@section('title', $kind === 'roi' ? 'My ROI History' : 'My Level Income History')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">{{ $kind === 'roi' ? 'My ROI History' : 'My Level Income History' }}</h1>
        <p class="ds-page-copy">Only your stored investment records are listed, shown as USDT. They are not package purchase credits, and new package income is not being calculated.</p>
    </header>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference" aria-label="Search your records" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2">
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'member'])
</div>
@endsection
