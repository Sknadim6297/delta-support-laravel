@extends('member.layouts.app')
@section('title', 'My Referral Link')
@section('page-title', 'My Referral Link')
@section('content-class', 'lh-native')

@section('content')
<div class="row">
    <div class="col-xl-4 col-md-6">
        <div class="lh-card lh-card-1">
            <div class="lh-card-content label-card">
                <div class="title">
                    <div class="growth-numbers">
                        <h4>Member Name</h4>
                        <h5>{{ $member->member_name }}</h5>
                    </div>
                    <span class="icon"><i class="ri-user-line"></i></span>
                </div>
                <p class="card-groth"><span>Member ID {{ $member->member_id }}</span></p>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="lh-card lh-card-4">
            <div class="lh-card-content label-card">
                <div class="title">
                    <div class="growth-numbers">
                        <h4>Direct referrals</h4>
                        <h5>{{ $directCount }}</h5>
                    </div>
                    <span class="icon"><i class="ri-team-line"></i></span>
                </div>
                <p class="card-groth"><span>People who registered with your Member ID</span></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="lh-card">
            <div class="lh-card-header">
                <h4 class="lh-card-title">Referral link</h4>
            </div>
            <div class="lh-card-content">
                <p class="lh-note">Share this link. A registration uses your Member ID as the sponsor and starts inactive. It does not purchase a package or create income.</p>
                <div id="referral-link" class="lh-pay-address">{{ $link }}</div>
                <p class="lh-note" data-copy-status role="status" hidden></p>
                <div class="lh-filter">
                    <button type="button" class="lh-primary-btn" data-copy-text="{{ $link }}"><i class="ri-file-copy-line"></i> Copy Link</button>
                    <button type="button" class="lh-primary-btn" data-share-url="{{ $link }}" data-share-title="Join Delta Support" data-share-text="Register with my Delta Support referral link."><i class="ri-share-line"></i> Share Link</button>
                    <a class="lh-primary-btn" href="{{ route('member.registration') }}"><i class="ri-user-add-line"></i> Register New Member</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
