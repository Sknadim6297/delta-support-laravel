@extends('admin.layouts.app')
@section('title', 'Level Unlock Rules')
@section('page-title', 'Level Unlock Rules')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Level Unlock Rules"
        lead="A qualifying direct is a sponsored member with an approved {{ implode(' or ', $qualifyingTypes) }}. A tour-only package does not qualify. This chart does not pay commission."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.finance.eligibility') }}" variant="secondary">Open Referral Eligibility</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Qualifying directs</th>
                    <th>Unlocked levels</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($rules as $rule)
                <tr>
                    <td>{{ $rule['directs'] }}</td>
                    <td>{{ $rule['levels'] }} of 20</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </x-ui.table>
</div>
@endsection
