<div class="ds-preview-banner" role="status">
    <div>
        <strong>Admin Preview Mode</strong>
        <p>You are viewing the account of {{ $member->member_id }} — {{ $member->member_name }}.</p>
    </div>
    <form method="POST" action="{{ route('admin.impersonation.stop') }}">
        @csrf
        <button type="submit">Back to Admin Panel</button>
    </form>
</div>
