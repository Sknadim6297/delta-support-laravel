@extends('admin.layouts.app')
@section('title', $kind === 'roi' ? 'ROI History' : 'Level Income History')
@section('page-title', $kind === 'roi' ? 'ROI History' : 'Level Income History')
@section('content')
<div class="ds-finance">
    <p class="ds-lead">Stored real income. Demonstration accruals are not listed here. The demonstration preview is a separate page and does not create these rows.</p>
    <form class="ds-searchbar" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference, member ID, or name" aria-label="Search income records">
        <button type="submit">Search</button>
        @if (request()->filled('q'))
            <a class="ds-btn ds-btn-secondary" href="{{ $kind === 'roi' ? route('admin.finance.roi-history') : route('admin.finance.level-income') }}">Reset</a>
        @endif
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'admin'])
</div>
@endsection
