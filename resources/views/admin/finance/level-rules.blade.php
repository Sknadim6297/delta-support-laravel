@extends('admin.layouts.app')
@section('title', 'Level Unlock Rules')
@section('page-title', 'Level Unlock Rules')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Level Unlock Rules"
        lead="A qualifying direct is a sponsored member with an approved {{ implode(' or ', $qualifyingTypes) }}. Registration alone does not activate. This chart does not pay commission. Live posting still uses the legacy ladder until posting is authorized on a published configuration."
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.finance.commission-settings', ['tab' => 'unlock']) }}" variant="secondary">Edit in Commission Settings</x-ui.button>
            <x-ui.button href="{{ route('admin.finance.eligibility') }}" variant="secondary">Open Referral Eligibility</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <x-ui.stat label="Live source" :value="$liveUsesHardcoded ? 'Hard-coded legacy' : 'Authorized DB'" hint="financial:daily unlock resolver" />
        <x-ui.stat label="Published" :value="$published?->version_code ?? 'None'" hint="{{ $published?->label ?? '—' }}" />
        <x-ui.stat label="Proposed draft" :value="$proposed?->version_code ?? 'None'" hint="Not active for posting" />
    </div>

    <x-ui.card title="Live unlock ladder" subtitle="Current financial behavior (legacy until authorized).">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Qualifying directs</th>
                        <th>Live unlocked levels</th>
                        <th>Proposed draft (if activated)</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td>{{ $rule['directs'] }}</td>
                        <td>{{ $rule['levels'] }} of 20</td>
                        <td>{{ $rule['proposed_active'] ?? '—' }} of 20</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Read-only impact preview" subtitle="Does not recalculate historical income or change wallets.">
        <x-ui.table>
            <table>
                <thead>
                    <tr>
                        <th>Scenario</th>
                        <th>Legacy</th>
                        <th>Proposed candidate</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($preview['scenarios'] as $scenario)
                    <tr>
                        <td>{{ $scenario['label'] }}</td>
                        <td>{{ $scenario['legacy_unlocked'] }}</td>
                        <td>{{ $scenario['candidate_unlocked'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-ui.table>
        <p>{{ $preview['historical_protection'] }}</p>
    </x-ui.card>
</div>
@endsection
