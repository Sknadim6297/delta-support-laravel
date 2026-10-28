<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->is_admin === true && $user->must_change_password === true) {
            if (! $request->routeIs('admin.change-password.*', 'admin.logout')) {
                return redirect()->route('admin.change-password.index');
            }
        }

        return $next($request);
    }
}
