<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectImpersonatingAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($request->routeIs('admin.members.impersonate')) {
            abort(403, 'An admin preview is already active.');
        }

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return redirect()->route('member.dashboard');
        }

        abort(403, 'Admin actions are unavailable while previewing a member account.');
    }
}
