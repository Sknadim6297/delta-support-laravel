@extends('admin.layouts.app')
@section('content')
<div class="mx-auto max-w-3xl">
    <header class="mb-5">
        <h1 class="ds-page-title">Admin Profile</h1>
        <p class="ds-page-copy">The account currently signed in to the admin panel.</p>
    </header>
    <div class="ds-card">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold text-navy-900">Name</dt><dd class="mt-1">{{ $user->name }}</dd></div>
            <div><dt class="font-semibold text-navy-900">Email</dt><dd class="mt-1">{{ $user->email }}</dd></div>
        </dl>
    </div>
</div>
@endsection
