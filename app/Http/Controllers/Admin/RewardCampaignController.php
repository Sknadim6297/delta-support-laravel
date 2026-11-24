<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RewardCampaignRequest;
use App\Models\RewardCampaign;
use App\Services\Rewards\RewardCampaignLifecycle;
use App\Services\Rewards\RewardCampaignMedia;
use App\Services\Rewards\RewardEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RewardCampaignController extends Controller
{
    public function __construct(private readonly RewardCampaignMedia $media)
    {
    }

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
            'campaign' => new RewardCampaign([
                'denomination' => 'USDT',
                'promote_on_login' => true,
            ]),
        ]);
    }

    public function store(RewardCampaignRequest $request): RedirectResponse
    {
        $campaign = new RewardCampaign($request->campaignAttributes());
        $campaign->status = RewardCampaign::DRAFT;
        $campaign->created_by = $request->user()->id;
        $campaign->save();
        $this->applyMedia($request, $campaign);

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
        $this->applyMedia($request, $campaign);

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

    public function destroy(Request $request, RewardCampaign $campaign): RedirectResponse
    {
        abort_unless($request->user()?->is_admin === true, 403);

        if (! in_array($campaign->status, [RewardCampaign::DRAFT, RewardCampaign::CANCELLED], true)) {
            return back()->withErrors([
                'status' => 'Only draft or cancelled campaigns can be permanently deleted.',
            ]);
        }

        $image = $campaign->gift_image_path;
        $video = $campaign->video_path;
        $campaign->delete();
        $this->media->deleteOrphanPaths([$image, $video]);

        return redirect()
            ->route('admin.rewards.index')
            ->with('success', 'Campaign permanently deleted. Owned media files were removed when unreferenced.');
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
        abort_unless($this->media->isSafeImagePath($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    public function video(RewardCampaign $campaign): StreamedResponse|BinaryFileResponse
    {
        $path = (string) $campaign->video_path;
        abort_unless($this->media->isSafeVideoPath($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }

    private function applyMedia(RewardCampaignRequest $request, RewardCampaign $campaign): void
    {
        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            $this->media->removeBanner($campaign);
        } elseif ($request->hasFile('image')) {
            $this->media->storeBanner($campaign, $request->file('image'));
        }

        if ($request->boolean('remove_video') && ! $request->hasFile('video')) {
            $this->media->removeVideo($campaign);
        } elseif ($request->hasFile('video')) {
            $this->media->storeVideo($campaign, $request->file('video'));
        }
    }
}
