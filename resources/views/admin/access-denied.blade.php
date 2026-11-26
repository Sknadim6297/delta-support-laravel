@extends('admin.layouts.app')
@php
    $reason = (string) ($reason ?? session('access_denied_reason', request('reason', 'page')));
    $isNoPermissions = $reason === 'none';
    $isInactive = $reason === 'inactive';
    $title = $isNoPermissions ? 'Access not assigned' : 'Access denied';
    $message = match (true) {
        $isInactive => 'This administrator account is inactive.',
        $isNoPermissions => 'Your Sub-Admin account does not have permission to open any panel modules. Contact the Main Admin to update your access.',
        default => "You don't have the access of this page. Sorry.",
    };
@endphp
@section('title', $title)
@section('page-title', $title)
@section('content')
<div class="ds-catalog ds-subadmin">
    <x-ui.page-header
        kicker="Security"
        :title="$title"
        :lead="$message"
    />

    <x-ui.card>
        <p class="ds-muted" style="margin:0 0 1rem;">
            @if ($isNoPermissions || $isInactive)
                You can sign out and contact the Main Admin for help.
            @else
                Ask the Main Admin if you need access to this module.
            @endif
        </p>
        <div class="ds-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
            @php
                $home = app(\App\Services\AdminAccess::class)->firstAuthorizedUrl(auth()->user());
            @endphp
            @if ($home && ! str_contains((string) $home, 'access-denied'))
                <x-ui.button :href="$home" variant="secondary">Go to available page</x-ui.button>
            @endif
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">Log out</x-ui.button>
            </form>
        </div>
    </x-ui.card>
</div>
@endsection
