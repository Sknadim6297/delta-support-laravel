@extends('admin.layouts.app')

@section('title', 'Referral Network')
@section('page-title', 'Referral Network')

@section('content')
    @include('shared.genealogy-workspace', [
        'formId' => 'adminTreeForm',
        'inputId' => 'memberTreeId',
        'formAction' => route('admin.genealogy.tree-view'),
        'resetUrl' => route('admin.genealogy.tree-view'),
        'showReset' => filled($selectedMemberId ?? null),
        'crumbParent' => 'Member Management',
        'crumbParentUrl' => route('admin.members.directory'),
        'profileBase' => route('admin.members.update'),
        'searchHint' => 'Enter a member ID, or leave the field blank to show the full hierarchy.',
    ])
@endsection
