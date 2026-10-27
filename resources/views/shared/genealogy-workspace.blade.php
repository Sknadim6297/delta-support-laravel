@php
    $selectedMemberId = $selectedMemberId ?? '';
    $memberNotFound = $memberNotFound ?? false;
    $showReset = $showReset ?? false;
@endphp

<div class="ds-stack" data-genealogy>
    <x-ui.page-header
        title="Referral Network"
        lead="View sponsor relationships and direct member hierarchy."
    >
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => $crumbParent, 'href' => $crumbParentUrl],
                ['label' => 'Referral Tree'],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <form method="GET" action="{{ $formAction }}" id="{{ $formId }}" class="ds-searchbar" data-genealogy-search>
        <label for="{{ $inputId }}">Member ID</label>
        <input
            type="text"
            id="{{ $inputId }}"
            name="member_id"
            value="{{ $selectedMemberId }}"
            placeholder="Enter member ID"
            data-member-autocomplete
            autocomplete="off"
            @if ($memberNotFound) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif
        >
        <button type="submit">
            <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span>Search</span>
        </button>
        @if ($showReset)
            <a class="ds-btn ds-btn-secondary" href="{{ $resetUrl }}">Reset</a>
        @endif
        @if ($memberNotFound)
            <p class="validation-message" id="{{ $inputId }}-error">Member not found.</p>
        @elseif (! empty($searchHint))
            <p class="ds-hint">{{ $searchHint }}</p>
        @endif
    </form>

    <section class="tree-workspace" aria-label="Referral network">
        @include('shared.genealogy-controls')

        <div id="treeContainer" class="tree-canvas" tabindex="0" aria-label="Referral tree canvas">
            @if ($memberNotFound)
                <x-ui.empty title="Member not found." message="Check the member ID and search again. No hierarchy is shown for an unknown member.">
                    <x-slot:icon>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    </x-slot:icon>
                </x-ui.empty>
            @elseif (empty($tree))
                <x-ui.empty title="No members found." message="Registered members will appear here with their sponsor relationships.">
                    <x-slot:icon>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/></svg>
                    </x-slot:icon>
                </x-ui.empty>
            @else
                <div class="tree-stage" data-tree-stage>
                    <div class="tree-scaler" data-tree-scaler>
                    <ul class="member-tree" aria-label="Sponsor hierarchy">
                        @foreach ($tree as $node)
                            @include('shared.genealogy-node', [
                                'node' => $node,
                                'profileBase' => $profileBase ?? null,
                                'ownProfileUrl' => $ownProfileUrl ?? null,
                                'profileFor' => $profileFor ?? null,
                            ])
                        @endforeach
                    </ul>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <x-ui.dialog id="treeDetailDialog" class="tree-detail-dialog" title="Member details">
        <dl class="tree-detail-list">
            <dt>Name</dt>
            <dd data-detail="name"></dd>
            <dt>Member ID</dt>
            <dd data-detail="member"></dd>
            <dt>Status</dt>
            <dd data-detail="status"></dd>
            <dt>Direct referrals</dt>
            <dd data-detail="count"></dd>
        </dl>
        <x-slot:actions>
            <a data-detail="profile" class="ds-btn" hidden>View profile</a>
            <x-ui.button type="submit" variant="secondary">Close</x-ui.button>
        </x-slot:actions>
    </x-ui.dialog>
</div>
