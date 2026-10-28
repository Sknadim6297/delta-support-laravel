<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MemberImpersonation
{
    public function active(Request $request): bool
    {
        return $request->session()->has('impersonator_id');
    }

    public function start(Request $request, Member $member): void
    {
        $admin = $request->user();

        if (! $admin || $admin->is_admin !== true) {
            throw new AccessDeniedHttpException('Only an administrator can preview a member account.');
        }

        if ($this->active($request)) {
            throw new AccessDeniedHttpException('An admin preview is already active.');
        }

        $log = ImpersonationLog::query()->create([
            'admin_id' => $admin->id,
            'member_id' => $member->member_id,
            'action' => 'started',
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $admin->id);
        $request->session()->put('impersonation_log_id', $log->id);
        $request->session()->put('member_context_id', $member->member_id);
    }

    public function stop(Request $request): void
    {
        $admin = $request->user();
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! $admin || $admin->is_admin !== true || (int) $admin->id !== (int) $impersonatorId) {
            throw new AccessDeniedHttpException('The original admin session is not available.');
        }

        $this->finish($request);
        $request->session()->regenerate();
    }

    public function finish(Request $request): void
    {
        $logId = $request->session()->get('impersonation_log_id');

        if ($logId) {
            ImpersonationLog::query()
                ->whereKey($logId)
                ->whereNull('ended_at')
                ->update([
                    'action' => 'ended',
                    'ended_at' => now(),
                ]);
        }

        $request->session()->forget([
            'impersonator_id',
            'impersonation_log_id',
            'member_context_id',
        ]);
    }
}
