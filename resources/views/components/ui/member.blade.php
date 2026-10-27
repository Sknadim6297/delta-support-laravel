@props(['name', 'id', 'status' => null])

<div {{ $attributes->merge(['class' => 'ds-member']) }}>
    <span class="ds-avatar" aria-hidden="true">{{ strtoupper(substr($name, 0, 1)) }}</span>
    <span>
        <strong>{{ $name }}</strong>
        <small>{{ $id }}</small>
    </span>
    @if ($status)
        <x-ui.badge :tone="strtolower($status) === 'active' ? 'success' : 'warning'">{{ ucfirst($status) }}</x-ui.badge>
    @endif
</div>
