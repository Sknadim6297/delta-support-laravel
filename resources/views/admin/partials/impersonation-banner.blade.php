@php
    $previewGrant = auth()->user()?->is_admin
        ? app(\App\Services\MemberPreviewGuard::class)->activeForAdmin((int) auth()->id())
        : null;
    $previewMember = $previewGrant
        ? \App\Models\Member::query()->where('member_id', $previewGrant->member_id)->first()
        : null;
@endphp
@if ($previewGrant && $previewMember)
    <div class="ds-preview-banner" role="status">
        <div>
            <strong>Member preview active</strong>
            <p>
                Isolated preview for {{ $previewMember->member_id }} — {{ $previewMember->member_name }}.
                Admin session is separate. Open the member panel in another tab.
            </p>
        </div>
        <div class="ds-preview-banner-actions">
            <form method="POST" action="{{ route('admin.members.preview.open') }}" target="_blank">
                @csrf
                <button type="submit" class="ds-btn ds-btn-sm">Open member panel</button>
            </form>
            <form method="POST" action="{{ route('admin.impersonation.stop') }}">
                @csrf
                <button type="submit">End preview</button>
            </form>
        </div>
    </div>
@endif
