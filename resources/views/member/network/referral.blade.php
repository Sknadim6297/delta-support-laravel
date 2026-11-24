@extends('member.layouts.app')
@section('title', 'My Referral Link')
@section('page-title', 'My Referral Link')

@section('content')
<div class="ds-finance">
    <x-ui.page-header
        kicker="Network"
        title="My Referral Link"
        lead="Share this link. A registration uses your Member ID as the sponsor and starts inactive. It does not purchase a package or create income."
        :heading="true"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('member.registration') }}">Register New Member</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-stat-grid">
        <x-ui.stat icon="ri-user-line" label="Member Name" :value="$member->member_name" :hint="'Member ID '.$member->member_id" />
        <x-ui.stat icon="ri-team-line" tone="teal" label="Direct referrals" :value="(string) $directCount" hint="People who registered with your Member ID" />
        <x-ui.stat icon="ri-links-line" tone="violet" label="Sponsor ID" :value="$member->member_id" hint="Used on new registrations" />
        <x-ui.stat icon="ri-share-forward-line" tone="amber" label="Share ready" value="Yes" hint="Copy or share the link below" />
    </div>

    <x-ui.card title="Referral link" subtitle="Copy or share this URL with new members.">
        <div class="pv-copy-row">
            <input type="text" readonly value="{{ $link }}" id="referral-link" aria-label="Referral link">
            <button type="button" class="ds-btn ds-btn-secondary" data-copy-text="{{ $link }}"><i class="ri-file-copy-line" aria-hidden="true"></i> Copy Link</button>
            <button type="button" class="ds-btn" data-share-url="{{ $link }}" data-share-title="Join Delta Support" data-share-text="Register with my Delta Support referral link."><i class="ri-share-line" aria-hidden="true"></i> Share Link</button>
        </div>
        <p class="ds-lead" data-copy-status role="status" hidden></p>
    </x-ui.card>
</div>
@endsection
