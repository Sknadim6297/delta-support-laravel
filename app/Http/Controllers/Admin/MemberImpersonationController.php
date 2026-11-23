<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberImpersonation;
use App\Services\MemberPreviewGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberImpersonationController extends Controller
{
    public function start(Request $request, Member $member, MemberImpersonation $impersonation): RedirectResponse
    {
        $started = $impersonation->start($request, $member);

        return redirect()
            ->route('member.dashboard')
            ->withCookie($started['cookie']);
    }

    public function stop(Request $request, MemberImpersonation $impersonation): RedirectResponse
    {
        $stopped = $impersonation->stop($request);

        return redirect()
            ->route('admin.members.directory')
            ->with('success', 'Member preview ended. Admin session is unchanged.')
            ->withCookie($stopped['cookie']);
    }

    public function stopPreviewOnly(Request $request, MemberImpersonation $impersonation): RedirectResponse
    {
        abort_unless(\App\Support\MemberContext::isPreview($request), 403);

        $stopped = $impersonation->stop($request);
        $adminStillLoggedIn = $request->user()?->is_admin === true;

        return redirect()
            ->route($adminStillLoggedIn ? 'admin.members.directory' : 'login')
            ->with('success', 'Member preview ended.')
            ->withCookie($stopped['cookie']);
    }

    /**
     * Re-attach the preview cookie for an already-issued grant (Open member panel).
     */
    public function open(Request $request, MemberPreviewGuard $preview, MemberImpersonation $impersonation): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin?->is_admin === true, 403);

        $grant = $preview->activeForAdmin((int) $admin->id);
        if ($grant === null) {
            return redirect()
                ->route('admin.members.directory')
                ->withErrors(['preview' => 'No active member preview. Use Login as Member first.']);
        }

        $member = Member::query()->where('member_id', $grant->member_id)->firstOrFail();
        $started = $impersonation->start($request, $member);

        return redirect()
            ->route('member.dashboard')
            ->withCookie($started['cookie']);
    }
}
