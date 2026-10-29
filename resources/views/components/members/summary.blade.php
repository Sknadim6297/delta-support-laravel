@props(['total', 'active', 'inactive', 'approved' => null])

<div class="ds-mm-stats">
    <article class="ds-mm-stat">
        <span>Total members</span>
        <strong>{{ number_format($total) }}</strong>
        <small>Every saved account</small>
    </article>
    <article class="ds-mm-stat">
        <span>Active</span>
        <strong>{{ number_format($active) }}</strong>
        <small>Status is active</small>
    </article>
    <article class="ds-mm-stat">
        <span>Inactive</span>
        <strong>{{ number_format($inactive) }}</strong>
        <small>Status is inactive</small>
    </article>
    @if ($approved !== null)
        <article class="ds-mm-stat">
            <span>Approved packages</span>
            <strong>{{ number_format($approved) }}</strong>
            <small>Members with a verified purchase</small>
        </article>
    @endif
</div>
