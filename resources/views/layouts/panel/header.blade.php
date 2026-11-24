@php
    $initial = strtoupper(substr((string) ($pvName ?? 'U'), 0, 1));
@endphp
<header class="pv-header">
    <div class="pv-header-start">
        <button type="button" class="pv-icon-btn" data-pv-sidebar-toggle aria-label="Toggle navigation" aria-expanded="false">
            <i class="ri-menu-line" aria-hidden="true"></i>
        </button>
        <div class="pv-breadcrumb">
            <div class="pv-breadcrumb-path">
                @if (! empty($pvHome))
                    <a href="{{ $pvHome }}">Home</a>
                    <span aria-hidden="true">/</span>
                @endif
                <span>{{ $pvTitle ?? 'Panel' }}</span>
            </div>
            <h1>{{ $pvTitle ?? 'Panel' }}</h1>
        </div>
    </div>
    <div class="pv-header-end">
        @if (! empty($headerBalance) && isset($headerBalance['amount']))
            <div
                class="pv-balance"
                title="{{ $headerBalance['hint'] ?? 'Withdrawable available balance' }}"
                aria-label="{{ \App\Support\MoneyDisplay::dollar($headerBalance['amount']) }}"
            >
                <i class="ri-wallet-3-line" aria-hidden="true"></i>
                <strong>{{ \App\Support\MoneyDisplay::dollar($headerBalance['amount']) }}</strong>
            </div>
        @endif

        <div class="pv-menu" data-pv-menu>
            <button type="button" class="pv-icon-btn" data-pv-menu-button aria-label="Notifications" aria-expanded="false">
                <i class="ri-notification-2-line" aria-hidden="true"></i>
            </button>
            <div class="pv-menu-panel" role="menu">
                @forelse ($supportNotices ?? [] as $notice)
                    <a href="{{ $notice->audience === \App\Models\SupportNotification::AUDIENCE_MEMBER ? route('member.support.show', $notice->support_ticket_id) : route('admin.support.show', $notice->support_ticket_id) }}">{{ $notice->summary }}</a>
                @empty
                    <p>No new notifications</p>
                @endforelse
            </div>
        </div>

        <div class="pv-menu" data-pv-menu>
            <button type="button" class="pv-user-btn" data-pv-menu-button aria-label="Account menu" aria-expanded="false">
                <span class="pv-avatar">{{ $initial }}</span>
                <span class="pv-user-copy">
                    <strong>{{ $pvName }}</strong>
                    @if (! empty($pvMeta))
                        <small>{{ $pvMeta }}</small>
                    @endif
                </span>
                <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
            </button>
            <div class="pv-menu-panel" role="menu">
                @if (! empty($pvProfile))
                    <a href="{{ $pvProfile }}">{{ $pvProfileLabel ?? 'Profile' }}</a>
                @endif
                @if (! empty($pvPassword))
                    <a href="{{ $pvPassword }}">Change password</a>
                @endif
                <form action="{{ $pvLogout }}" method="POST">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            </div>
        </div>
    </div>
</header>
