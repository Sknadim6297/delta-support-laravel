@extends('admin.layouts.app')
@section('title', 'Commission Settings')
@section('page-title', 'Commission Settings')
@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Commission Settings"
        lead="Stored Delta Support plan only. The plan stays inactive, and this page does not generate level income."
    />
    <div class="ds-stat-grid">
        <x-ui.stat label="Levels stored" :value="$levels->count()" hint="Rows in the stored plan" />
        <x-ui.stat label="Combined rate" :value="$total.'%'" hint="Sum of stored percentages" />
        <x-ui.stat label="Plan status" :value="$active ? 'Active' : 'Inactive'" hint="Inactive plans do not pay commission" />
    </div>
    @if ($errors !== [])
        <x-ui.alert type="warning">
            @foreach ($errors as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif
    <x-ui.table>
        <table>
            <thead>
                <tr>
                    <th>Level</th>
                    <th class="ds-money">Percentage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($levels as $level)
                <tr>
                    <td>{{ $level->level }}</td>
                    <td class="ds-money">{{ $level->percentage }}%</td>
                    <td><x-ui.badge :tone="$level->is_active ? 'success' : 'muted'">{{ $level->is_active ? 'Active' : 'Inactive' }}</x-ui.badge></td>
                </tr>
            @empty
                <tr><td class="ds-empty-cell" colspan="3">No commission plan is stored.</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-ui.table>
</div>
@endsection
