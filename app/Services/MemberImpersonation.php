<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use App\Support\MemberContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Admin "Login as Member" facade.
 * Delegates to MemberPreviewGuard — never Auth::login($member), never mutates admin guard identity.
 */
class MemberImpersonation
{
    public function __construct(private readonly MemberPreviewGuard $preview)
    {
    }

    public function active(Request $request): bool
    {
        return $this->preview->active($request)
            || $this->preview->activeForAdmin((int) ($request->user()?->id ?? 0)) !== null;
    }

    /**
     * @return array{cookie: Cookie}
     */
    public function start(Request $request, Member $member): array
    {
        $admin = $request->user();

        if (! $admin instanceof User || $admin->is_admin !== true) {
            throw new AccessDeniedHttpException('Only an administrator can preview a member account.');
        }

        // Keep the admin web session free of member identity.
        if ($request->hasSession()) {
            $request->session()->forget([
                'member_context_id',
                'impersonator_id',
                'impersonation_log_id',
            ]);
        }

        $issued = $this->preview->issue($admin, $member, $request);

        return ['cookie' => $this->preview->makeCookie($issued['plain_token'])];
    }

    /**
     * @return array{cookie: Cookie}
     */
    public function stop(Request $request): array
    {
        $admin = $request->user();
        $previewAdminId = MemberContext::previewAdminId($request);

        if ($admin instanceof User && $admin->is_admin === true) {
            $this->preview->revokeForAdmin((int) $admin->id);
        } elseif ($previewAdminId !== null) {
            // Preview cookie alone may end its own grant without touching Auth.
            $this->preview->revokeForAdmin($previewAdminId);
        } else {
            throw new AccessDeniedHttpException('No active member preview to end.');
        }

        if ($request->hasSession() && ! ($admin instanceof User && $admin->is_admin === true)) {
            // Do not clear an unrelated member_context_id belonging to a normal member login.
        } elseif ($request->hasSession() && $admin instanceof User && $admin->is_admin === true) {
            $request->session()->forget([
                'impersonator_id',
                'impersonation_log_id',
            ]);
        }

        return ['cookie' => $this->preview->forgetCookie()];
    }

    public function finish(Request $request): void
    {
        $admin = $request->user();
        if ($admin instanceof User && $admin->is_admin === true) {
            $this->preview->revokeForAdmin((int) $admin->id);
        } elseif (MemberContext::isPreview($request)) {
            $this->preview->revokeFromRequest($request);
        }

        if ($request->hasSession()) {
            $request->session()->forget([
                'member_context_id',
                'impersonator_id',
                'impersonation_log_id',
            ]);
        }
    }

    public function forgetCookie(): Cookie
    {
        return $this->preview->forgetCookie();
    }
}
