<?php

namespace App\Http\Middleware;

use App\Support\MemberContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventImpersonatedMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MemberContext::isPreview($request)) {
            return $next($request);
        }

        if ($request->routeIs('admin.impersonation.stop', 'member.logout', 'admin.logout', 'member.preview.stop')) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            abort(403, 'This action is unavailable while previewing a member account.');
        }

        return $next($request);
    }
}
