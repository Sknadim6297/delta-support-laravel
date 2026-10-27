@extends('member.layouts.app')

@section('title', 'Referral Network')
@section('page-title', 'Referral Network')

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
        'searchHint' => 'Search is limited to your own network.',
    ])
@endsection
