<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventImpersonatedMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($request->routeIs('admin.impersonation.stop', 'member.logout', 'admin.logout')) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'This action is unavailable while previewing a member account.');
        }

        return $next($request);
    }
}
