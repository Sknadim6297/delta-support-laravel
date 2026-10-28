<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardDelivery;
use App\Models\RewardQualification;
use App\Services\Rewards\RewardEligibilityQuery;
use App\Services\Rewards\RewardFulfillmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardEligibilityController extends Controller
{
    public function index(Request $request, RewardEligibilityQuery $query): View
    {
        return view('admin.rewards.eligible', [
            'qualifications' => $query->filtered($request)->paginate(25)->withQueryString(),
            'campaigns' => \App\Models\RewardCampaign::query()->orderByDesc('starts_on')->orderByDesc('id')->get(['id', 'title']),
        ]);
    }

    public function show(RewardQualification $qualification, RewardFulfillmentService $fulfillment): View
    {
        $qualification->load(['campaign', 'member', 'assignment.delivery.events.user']);

        return view('admin.rewards.qualification', [
            'qualification' => $qualification,
            'assignment' => $qualification->assignment,
            'delivery' => $qualification->assignment?->delivery,
            'choices' => $qualification->assignment?->delivery
                ? $fulfillment->choices($qualification->assignment->delivery)
                : [],
        ]);
    }

    public function updateDelivery(Request $request, RewardQualification $qualification, RewardFulfillmentService $fulfillment): RedirectResponse
    {
        $delivery = $qualification->assignment?->delivery;
        abort_unless($delivery instanceof RewardDelivery, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', RewardDelivery::STATUSES)],
            'method' => ['nullable', 'string', 'max:120'],
            'tracking_reference' => ['nullable', 'string', 'max:160'],
            'dispatched_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'recipient_confirmation' => ['nullable', 'string', 'max:160'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $fulfillment->transition($delivery, $data, $request->user());

        return back()->with('success', 'Delivery status saved.');
    }
}
