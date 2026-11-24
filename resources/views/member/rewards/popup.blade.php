@php
    /** @var \App\Models\RewardCampaign $rewardPopupCampaign */
    $hasBanner = $rewardPopupCampaign->hasBanner();
    $hasVideo = $rewardPopupCampaign->hasVideo();
@endphp
<dialog
    class="ds-modal ds-reward-popup"
    id="reward-campaign-popup"
    aria-labelledby="reward-popup-title"
    aria-describedby="reward-popup-lead"
    data-reward-popup
>
    <button type="button" class="ds-reward-popup-close" data-reward-popup-close aria-label="Close reward campaign">
        <i class="ri-close-line" aria-hidden="true"></i>
    </button>

    <div class="ds-reward-popup-layout">
        <div class="ds-reward-popup-media @if(! $hasBanner) is-fallback @endif">
            @if ($hasBanner)
                <img
                    src="{{ route('member.rewards.image', $rewardPopupCampaign) }}"
                    alt=""
                    class="ds-reward-popup-media-img"
                    width="720"
                    height="240"
                    decoding="async"
                >
            @else
                <div class="ds-reward-popup-fallback" aria-hidden="true">
                    <i class="ri-gift-line"></i>
                </div>
            @endif
        </div>

        <div class="ds-reward-popup-body">
            <span class="ds-reward-popup-eyebrow"><i class="ri-gift-2-line" aria-hidden="true"></i> Special Rewards Campaign</span>
            <h2 id="reward-popup-title">{{ $rewardPopupCampaign->title }}</h2>
            @if ($rewardPopupCampaign->description)
                <p id="reward-popup-lead" class="ds-reward-popup-lead">{{ \Illuminate\Support\Str::limit(strip_tags($rewardPopupCampaign->description), 140) }}</p>
            @else
                <p id="reward-popup-lead" class="ds-reward-popup-lead">Grow your team business and win {{ $rewardPopupCampaign->gift_name }}.</p>
            @endif

            <div class="ds-reward-popup-facts">
                <div>
                    <i class="ri-gift-line" aria-hidden="true"></i>
                    <div>
                        <strong>Gift</strong>
                        <span>{{ $rewardPopupCampaign->gift_name }}</span>
                    </div>
                </div>
                <div>
                    <i class="ri-focus-3-line" aria-hidden="true"></i>
                    <div>
                        <strong>Target</strong>
                        <span>{{ \App\Support\MoneyDisplay::usdt($rewardPopupCampaign->target_amount) }} · Approved team business</span>
                    </div>
                </div>
                <div>
                    <i class="ri-calendar-line" aria-hidden="true"></i>
                    <div>
                        <strong>Period</strong>
                        <span>{{ $rewardPopupCampaign->starts_on->format('d M Y') }} – {{ $rewardPopupCampaign->ends_on->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            @if ($hasVideo)
                <div class="ds-reward-popup-video">
                    <video
                        controls
                        preload="none"
                        playsinline
                        @if($hasBanner) poster="{{ route('member.rewards.image', $rewardPopupCampaign) }}" @endif
                    >
                        <source data-src="{{ route('member.rewards.video', $rewardPopupCampaign) }}" type="video/mp4">
                    </video>
                    <button type="button" class="ds-reward-popup-play" data-reward-video-load aria-label="Load campaign video">
                        <i class="ri-play-fill" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        </div>

        <div class="ds-reward-popup-footer">
            <div class="ds-reward-popup-actions">
                <button type="button" class="ds-btn ds-btn-secondary" data-reward-popup-close>
                    Not Now
                </button>
                <a class="ds-btn" href="{{ route('member.rewards.show', $rewardPopupCampaign) }}">
                    View Campaign Details <i class="ri-arrow-right-line" aria-hidden="true"></i>
                </a>
            </div>
            <p class="ds-pkg-hint">Viewing this campaign does not change reward qualification.</p>
        </div>
    </div>
</dialog>
<script>
(function () {
    const dialog = document.getElementById('reward-campaign-popup');
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const closeButtons = dialog.querySelectorAll('[data-reward-popup-close]');
    const videoWrap = dialog.querySelector('.ds-reward-popup-video');
    const video = videoWrap ? videoWrap.querySelector('video') : null;
    const playBtn = dialog.querySelector('[data-reward-video-load]');
    const source = video ? video.querySelector('source') : null;
    const primaryAction = dialog.querySelector('.ds-reward-popup-actions .ds-btn:not(.ds-btn-secondary)');

    function closePopup() {
        if (video) {
            video.pause();
        }
        if (dialog.open) {
            dialog.close();
        }
    }

    closeButtons.forEach(function (btn) {
        btn.addEventListener('click', closePopup);
    });

    dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
            closePopup();
        }
    });

    dialog.addEventListener('cancel', function (event) {
        event.preventDefault();
        closePopup();
    });

    if (playBtn && video && source) {
        playBtn.addEventListener('click', function () {
            if (source.dataset.src && !source.getAttribute('src')) {
                source.setAttribute('src', source.dataset.src);
                video.load();
            }
            playBtn.hidden = true;
            video.setAttribute('controls', 'controls');
            video.play().catch(function () {});
        });
    }

    try {
        dialog.showModal();
    } catch (error) {
        dialog.setAttribute('open', '');
    }

    if (primaryAction) {
        primaryAction.focus();
    }
})();
</script>
