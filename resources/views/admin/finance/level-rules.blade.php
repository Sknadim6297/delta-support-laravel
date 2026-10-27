@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Level Unlock Rules</h1>
        <p>A qualifying direct is a sponsored member with an approved {{ implode(' or ', $qualifyingTypes) }}. A tour-only package does not qualify. This chart does not pay commission.</p>
    </header>
    <div>
        <table>
            <thead><tr><th>Qualifying directs</th><th>Unlocked levels</th></tr></thead>
            <tbody>
            @foreach ($rules as $rule)
                <tr>
                    <td>{{ $rule['directs'] }}</td>
                    <td>{{ $rule['levels'] }} of 20</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p><a href="{{ route('admin.finance.eligibility') }}">Open Referral Eligibility</a></p>
</div>
@endsection
