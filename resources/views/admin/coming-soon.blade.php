@extends('admin.layouts.app')
@section('title', $feature . ' - Coming Soon')
@section('page-title', $feature)
@section('content')
    <x-ui.page-header :title="$feature" lead="Coming Soon" />
    <x-ui.empty title="Coming Soon" message="This module is reserved for a later phase. No balances are credited from this page." />
@endsection
