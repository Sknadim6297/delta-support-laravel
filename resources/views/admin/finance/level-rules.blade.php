@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-4">
        <h1 class="ds-page-title">Level Unlock Rules</h1>
        <p class="ds-page-copy">A qualifying direct is a sponsored member with an approved {{ implode(' or ', $qualifyingTypes) }}. A tour-only package does not qualify. This chart does not pay commission.</p>
    </header>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-navy-900"><tr><th class="p-3">Qualifying directs</th><th class="p-3">Unlocked levels</th></tr></thead>
            <tbody>
            @foreach ($rules as $rule)
                <tr class="border-t border-slate-200">
                    <td class="p-3">{{ $rule['directs'] }}</td>
                    <td class="p-3">{{ $rule['levels'] }} of 20</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-4 text-sm"><a class="font-semibold text-brand-700" href="{{ route('admin.finance.eligibility') }}">Open Referral Eligibility</a></p>
</div>
@endsection
