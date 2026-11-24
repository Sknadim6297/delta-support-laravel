<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberPaymentControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberPaymentControlController extends Controller
{
    public function update(Request $request, string $memberId, MemberPaymentControlService $payments): RedirectResponse
    {
        if (! $request->user()?->is_admin) {
            abort(403);
        }

        Member::query()->where('member_id', $memberId)->firstOrFail();

        $data = $request->validate([
            'master_enabled' => ['required', 'boolean'],
            'roi_preference' => ['required', 'boolean'],
            'level_preference' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'confirm' => ['accepted'],
        ]);

        $payments->apply($memberId, [
            'master_enabled' => $request->boolean('master_enabled'),
            'roi_preference' => $request->boolean('roi_preference'),
            'level_preference' => $request->boolean('level_preference'),
            'reason' => $data['reason'],
            'effective_from' => $data['effective_from'],
            'confirm' => true,
        ], $request->user(), $request->ip());

        return redirect()
            ->route('admin.members.update', ['member_id' => $memberId])
            ->with('status', 'Payment controls saved. Financial posting applies these blocks only when member payment integration is authorized.');
    }
}
