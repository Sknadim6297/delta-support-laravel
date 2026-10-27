@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-6xl">
    <header class="mb-4">
        <h1 class="ds-page-title">{{ $kind === 'roi' ? 'ROI History' : 'Level Income History' }}</h1>
        <p class="ds-page-copy">Existing investment records only, shown as USDT. These rows are not package purchase credits, and package ROI is not being calculated.</p>
    </header>
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference, member ID, or name" aria-label="Search income records" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2">
        <button class="rounded-lg bg-navy-900 px-4 py-2 text-sm font-semibold text-white">Search</button>
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'admin'])
</div>
@endsection
