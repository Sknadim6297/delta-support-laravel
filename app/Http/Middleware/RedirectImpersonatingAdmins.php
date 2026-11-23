<?php

namespace App\Http\Middleware;

use App\Services\MemberPreviewGuard;
use App\Support\MemberContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preview cookie alone must never authorize Admin routes.
 * Admin identity remains the primary web guard only.
 */
class RedirectImpersonatingAdmins
{
    public function __construct(private readonly MemberPreviewGuard $preview)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Preview-only (no admin auth): hard block admin routes.
        if (MemberContext::isPreview($request) && $request->user()?->is_admin !== true) {
            abort(403, 'Member preview cannot access the admin panel.');
        }

        if ($request->routeIs('admin.members.impersonate')
            && $request->user()?->is_admin === true
            && $this->preview->activeForAdmin((int) $request->user()->id)
        ) {
            // Allow explicit replacement start — handled by issue() revoking prior grant.
            return $next($request);
        }

        return $next($request);
    }
}
