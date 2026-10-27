@php
    $children = $node['children'] ?? [];
    $childCount = count($children);
    $hasChildren = $childCount > 0;
    $status = strtolower((string) ($node['status'] ?? 'inactive'));
    $name = trim((string) ($node['name'] ?? ''));
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    $initials = mb_strtoupper(mb_substr($first, 0, 1).($second !== '' ? mb_substr($second, 0, 1) : mb_substr($first, 1, 1)));
    if ($initials === '') {
        $initials = 'M';
    }
    $shades = ['#0878D1', '#0664b0', '#0a4f86', '#092747', '#0e6aaf'];
    $shade = $shades[hexdec(substr(md5((string) ($node['id'] ?? $name)), 0, 2)) % count($shades)];
    $profileHref = null;
    if (! empty($profileBase)) {
        $profileHref = $profileBase.'?'.http_build_query(['member_id' => $node['id']]);
    } elseif (! empty($ownProfileUrl) && ! empty($profileFor) && (string) $profileFor === (string) ($node['id'] ?? '')) {
        $profileHref = $ownProfileUrl;
    }
@endphp

<li data-member-node data-member-id="{{ $node['id'] }}" data-member-status="{{ $status }}">
    <div class="tree-member">
        <article class="tree-card" style="--card-accent: {{ $status === 'active' ? '#0878D1' : '#94a3b8' }};">
            <div class="tree-card-top">
                <span class="tree-avatar" style="background: {{ $shade }}" aria-hidden="true">{{ $initials }}</span>
                <div class="tree-id">
                    <strong title="{{ $name }}">{{ $name }}</strong>
                    <small>{{ $node['id'] }}</small>
                </div>
            </div>
            <div class="tree-card-meta">
                <span class="{{ $status === 'active' ? 'status-active' : 'status-inactive' }}">{{ ucfirst($status) }}</span>
                @if ($hasChildren)
                    <span class="tree-count">{{ $childCount }} {{ $childCount === 1 ? 'child' : 'children' }}</span>
                @endif
            </div>
            <button
                type="button"
                class="tree-more"
                data-tree-detail
                data-name="{{ $name }}"
                data-member="{{ $node['id'] }}"
                data-status="{{ ucfirst($status) }}"
                data-count="{{ $childCount }}"
                @if ($profileHref) data-profile="{{ $profileHref }}" @endif
            >Details</button>
        </article>
        @if ($hasChildren)
            <button type="button" class="tree-toggle" data-tree-toggle aria-expanded="true" aria-label="Toggle {{ $name }} branch">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <ul class="tree-children">
            @foreach ($children as $child)
                @include('shared.genealogy-node', [
                    'node' => $child,
                    'profileBase' => $profileBase ?? null,
                    'ownProfileUrl' => $ownProfileUrl ?? null,
                    'profileFor' => $profileFor ?? null,
                ])
            @endforeach
        </ul>
    @endif
</li>
