@props(['action'])

<form class="ds-impersonate-form" method="POST" action="{{ $action }}" target="_blank">
    @csrf
    <button type="submit" class="ds-btn ds-btn-sm">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
        Login as Member
    </button>
</form>
