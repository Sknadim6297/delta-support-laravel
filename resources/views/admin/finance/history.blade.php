@extends('admin.layouts.app')
@section('title', $kind === 'roi' ? 'ROI History' : 'Level Income History')
@section('page-title', $kind === 'roi' ? 'ROI History' : 'Level Income History')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        :kicker="$kind === 'roi' ? 'ROI Management' : 'Level Income'"
        :title="$kind === 'roi' ? 'ROI History' : 'Level Income History'"
        lead="Existing investment records only, shown as USDT. These rows are not package purchase credits, and package ROI is not being calculated."
    />
    <form class="ds-filter" method="GET">
        <label>
            <span>Search</span>
            <input name="q" value="{{ request('q') }}" placeholder="Search reference, member ID, or name" aria-label="Search income records">
        </label>
        <div class="ds-filter-actions">
            <button type="submit">Search</button>
            @if (request()->filled('q'))
                <a class="ds-btn ds-btn-secondary" href="{{ $kind === 'roi' ? route('admin.finance.roi-history') : route('admin.finance.level-income') }}">Reset</a>
            @endif
        </div>
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'admin'])
</div>
@endsection
