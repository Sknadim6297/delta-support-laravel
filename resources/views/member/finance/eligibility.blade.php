@extends('member.layouts.app')
@section('title', 'My Referral Eligibility')
@section('content')
<div>
    <header>
        <h1>My Referral Eligibility</h1>
        <p>Levels follow your qualifying direct members. Commission is not being credited.</p>
    </header>
    <div>
        <article><p>Direct referrals</p><p>{{ $directCount }}</p></article>
        <article><p>Qualifying directs</p><p>{{ $qualifyingCount }}</p></article>
        <article><p>Unlocked levels</p><p>{{ $unlockedLevels }} of 20</p></article>
        <article><p>Status</p><p>{{ $status }}</p></article>
    </div>
    <section>
        <h2>Your referral link</h2>
        <div>
            <input type="text" readonly value="{{ $referralUrl }}" aria-label="Your referral link">
            <button type="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button>
        </div>
        <p>1 qualifying direct unlocks 4 levels, 2 unlock 8, 3 unlock 12, 4 unlock 16, and 5 or more unlock 20. A direct counts once, after an approved Tour + ROI or Premium package.</p>
    </section>
    <div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th>Qualifying package</th>
                </tr>
            </thead>
            <tbody>
            @forelse($directs as $direct)
                <tr>
                    <td>{{ $direct->member_id }}</td>
                    <td>{{ $direct->member_name }}</td>
                    <td>{{ in_array($direct->member_id, $qualifyingIds, true) ? 'Yes' : 'No' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No direct referrals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $directs->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
