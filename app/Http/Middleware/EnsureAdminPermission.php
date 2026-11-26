<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default route authorization for Sub-Admins.
 * Main Admin is unrestricted. Unmapped protected routes are denied to Sub-Admins.
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();
        $access = app(AdminAccess::class);

        if (! $access->isActiveAdmin($user)) {
            return $this->deny($request, 'inactive');
        }

        if ($access->isMainAdmin($user)) {
            return $next($request);
        }

        if ($permission !== null && $permission !== '') {
            if (! $access->can($user, $permission)) {
                return $this->deny($request, 'page');
            }

            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (! $access->allowsRoute($user, $routeName)) {
            return $this->deny($request, 'page');
        }

        return $next($request);
    }

    private function deny(Request $request, string $reason): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $reason === 'inactive'
                    ? 'This administrator account is inactive.'
                    : "You don't have the access of this page. Sorry.",
            ], 403);
        }

        // Avoid redirect loops on the access-denied page itself.
        if ($request->routeIs('admin.access-denied')) {
            return response()->view('admin.access-denied', [
                'reason' => $reason,
            ], 403);
        }

        return redirect()
            ->route('admin.access-denied', ['reason' => $reason === 'inactive' ? 'inactive' : 'page'])
            ->with('access_denied_reason', $reason === 'inactive' ? 'inactive' : 'page')
            ->with('status', "You don't have the access of this page. Sorry.");
    }
}
