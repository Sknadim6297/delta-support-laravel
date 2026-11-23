<div class="ds-preview-banner" role="status">
    <div>
        <strong>Admin Preview Mode</strong>
        <p>
            You are viewing {{ $member->member_id }} — {{ $member->member_name }}.
            This tab uses an isolated preview cookie. The Admin tab keeps the admin session.
            Changes are blocked in preview.
        </p>
    </div>
    <form method="POST" action="{{ route('member.preview.stop') }}">
        @csrf
        <button type="submit">End preview</button>
    </form>
</div>
