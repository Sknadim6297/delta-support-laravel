@extends('member.layouts.app')

@section('title', 'Referral Network')
@section('page-title', 'Referral Tree')
@section('content-class', 'lh-native')

@section('content')
    @include('shared.genealogy-workspace', [
        'formId' => 'memberTreeForm',
        'inputId' => 'memberTreeId',
        'formAction' => route('member.genealogy.tree-view'),
        'resetUrl' => route('member.genealogy.tree-view'),
        'showReset' => request()->filled('member_id'),
        'crumbParent' => 'My Network',
        'crumbParentUrl' => route('member.team.direct'),
        'ownProfileUrl' => route('member.profile'),
        'profileFor' => $member->member_id ?? null,
        'searchHint' => 'Search is limited to your own network. Direct referrals load first. Expand a branch to load the next level.',
        'childrenUrl' => route('member.genealogy.tree-children'),
    ])
@endsection
