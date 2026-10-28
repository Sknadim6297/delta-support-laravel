<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RewardCampaignRequest;
use App\Models\RewardCampaign;
use App\Services\Rewards\RewardCampaignLifecycle;
use App\Services\Rewards\RewardEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RewardCampaignController extends Controller
{
    public function index(): View
    {
        return view('admin.rewards.index', [
            'campaigns' => RewardCampaign::query()
                ->withCount([
                    'qualifications as selected_count' => fn ($query) => $query->where('status', 'selected'),
                ])
                ->orderByDesc('starts_on')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.rewards.form', [
            'campaign' => new RewardCampaign(['denomination' => 'USDT']),
        ]);
    }

    public function store(RewardCampaignRequest $request): RedirectResponse
    {
        $campaign = new RewardCampaign($request->campaignAttributes());
        $campaign->status = RewardCampaign::DRAFT;
        $campaign->created_by = $request->user()->id;
        $campaign->save();
        $this->storeImage($request, $campaign);

        return redirect()
            ->route('admin.rewards.show', $campaign)
            ->with('success', 'Draft campaign saved. Publish it when the dates and gift are ready.');
    }

    public function show(RewardCampaign $campaign): View
    {
        $campaign->loadCount([
            'qualifications as qualified_count' => fn ($query) => $query->whereIn('status', ['qualified', 'selected']),
            'qualifications as selected_count' => fn ($query) => $query->where('status', 'selected'),
        ]);

        return view('admin.rewards.show', [
            'campaign' => $campaign,
        ]);
    }

    public function edit(RewardCampaign $campaign): View
    {
        return view('admin.rewards.form', [
            'campaign' => $campaign,
        ]);
    }

    public function update(RewardCampaignRequest $request, RewardCampaign $campaign): RedirectResponse
    {
        $attributes = $request->campaignAttributes();
        if ($campaign->rulesLocked()) {
            $sameTarget = bccomp((string) $campaign->target_amount, (string) $attributes['target_amount'], 4) === 0;
            $sameStart = $campaign->starts_on->toDateString() === $attributes['starts_on'];
            $sameEnd = $campaign->ends_on->toDateString() === $attributes['ends_on'];
            if (! $sameTarget || ! $sameStart || ! $sameEnd) {
                throw ValidationException::withMessages([
                    'target_amount' => 'Target and dates stay fixed once a gift is processing, shipped, delivered, or failed.',
                ]);
            }
        }

        $campaign->fill($attributes)->save();
        $this->storeImage($request, $campaign);

        return redirect()
            ->route('admin.rewards.show', $campaign)
            ->with('success', 'Campaign details saved.');
    }

    public function publish(Request $request, RewardCampaign $campaign, RewardCampaignLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->publish($campaign, $request->user());

        return back()->with('success', 'Campaign status is now '.$campaign->refresh()->statusLabel().'.');
    }

    public function pause(Request $request, RewardCampaign $campaign, RewardCampaignLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->pause($campaign, $request->user());

        return back()->with('success', 'Campaign paused. Selected gifts were not removed.');
    }

    public function close(Request $request, RewardCampaign $campaign, RewardCampaignLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->close($campaign, $request->user());

        return back()->with('success', 'Campaign closed.');
    }

    public function cancel(Request $request, RewardCampaign $campaign, RewardCampaignLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->cancel($campaign, $request->user());

        return back()->with('success', 'Campaign cancelled. Committed gifts were left for manual review.');
    }

    public function evaluate(RewardCampaign $campaign, RewardEligibilityService $eligibility): RedirectResponse
    {
        if (! in_array($campaign->status, [RewardCampaign::ACTIVE, RewardCampaign::COMPLETED], true)) {
            return back()->withErrors([
                'status' => 'Only an active or completed campaign can be recalculated.',
            ]);
        }

        $result = $eligibility->evaluate($campaign->id);
        $message = $result['skipped']
            ? 'Another reward run is already in progress.'
            : 'Eligibility recalculated. Selected gifts: '.$result['selected'].'.';

        return back()->with('success', $message);
    }

    public function image(RewardCampaign $campaign): BinaryFileResponse
    {
        $path = (string) $campaign->gift_image_path;
        abort_unless($this->isRewardImagePath($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    private function storeImage(RewardCampaignRequest $request, RewardCampaign $campaign): void
    {
        if (! $request->hasFile('image')) {
            return;
        }

        $file = $request->file('image');
        $path = $file?->store('rewards', 'local');
        $storedMime = is_string($path) ? mime_content_type(Storage::disk('local')->path($path)) : false;
        if (! is_string($path) || ! in_array($storedMime, ['image/jpeg', 'image/png', 'image/webp'], true) || ! $this->isRewardImagePath($path)) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw ValidationException::withMessages([
                'image' => 'Upload a JPG, PNG, or WebP image.',
            ]);
        }

        $previous = $campaign->gift_image_path;
        $campaign->forceFill(['gift_image_path' => $path])->save();
        if (is_string($previous) && $previous !== $path && $this->isRewardImagePath($previous)) {
            Storage::disk('local')->delete($previous);
        }
    }

    private function isRewardImagePath(string $path): bool
    {
        return str_starts_with($path, 'rewards/') && ! str_contains($path, '..');
    }
}
