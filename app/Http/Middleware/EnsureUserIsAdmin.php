<?php

namespace App\Http\Middleware;

use App\Services\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_admin === true, 403);

        $access = app(AdminAccess::class);
        if (! $access->isActiveAdmin($user)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'This administrator account is inactive.',
                ], 403);
            }

            if ($request->routeIs('admin.access-denied')) {
                return response()->view('admin.access-denied', [
                    'reason' => 'inactive',
                ], 403);
            }

            return redirect()
                ->route('admin.access-denied', ['reason' => 'inactive'])
                ->with('access_denied_reason', 'inactive');
        }

        return $next($request);
    }
}
