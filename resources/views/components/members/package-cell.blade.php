@props([
    'tone' => 'muted',
    'badge' => 'Unpaid',
    'lines' => [],
])

<div class="ds-mm-package-cell">
    @foreach ($lines as $line)
        <div class="ds-mm-package-line">{{ $line }}</div>
    @endforeach
    <x-ui.badge :tone="$tone">{{ $badge }}</x-ui.badge>
</div>
