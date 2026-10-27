@extends('admin.layouts.app')
@section('content')
<div>
    <header>
        <h1>Security</h1>
        <p>Change the administrator password. Login attempts stay rate limited.</p>
    </header>
    <div>
        <a href="{{ route('admin.change-password.index') }}">Change password</a>
    </div>
</div>
@endsection
