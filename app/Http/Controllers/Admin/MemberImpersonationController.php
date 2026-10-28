<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\MemberImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemberImpersonationController extends Controller
{
    public function start(Request $request, Member $member, MemberImpersonation $impersonation): RedirectResponse
    {
        $impersonation->start($request, $member);

        return redirect()->route('member.dashboard');
    }

    public function stop(Request $request, MemberImpersonation $impersonation): RedirectResponse
    {
        $impersonation->stop($request);

        return redirect()
            ->route('admin.members.directory')
            ->with('success', 'Admin preview ended.');
    }
}
