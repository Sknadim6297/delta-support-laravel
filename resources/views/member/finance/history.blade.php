@extends('member.layouts.app')
@section('title', $kind === 'roi' ? 'My ROI History' : 'My Level Income History')
@section('page-title', $kind === 'roi' ? 'My ROI History' : 'My Level Income History')
@section('content')
<div class="ds-finance">
    <form class="ds-searchbar" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="Search reference" aria-label="Search your records">
        <button type="submit">Search</button>
        @if (request()->filled('q'))
            <a class="ds-btn ds-btn-secondary" href="{{ $kind === 'roi' ? route('member.income.roi') : route('member.income.level') }}">Reset</a>
        @endif
    </form>
    @include('partials.finance-history', ['kind' => $kind, 'rows' => $rows, 'audience' => 'member'])
</div>
@endsection
