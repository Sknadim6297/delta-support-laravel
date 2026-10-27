@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>{{ $kind === 'roi' ? 'ROI History' : 'Level Income History' }}</h1>
        <p>Existing investment records only, shown as USDT. These rows are not package purchase credits, and package ROI is not being calculated.</p>
    </header>
    <form method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference, member ID, or name" aria-label="Search income records">
        <button>Search</button>
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'admin'])
</div>
@endsection
