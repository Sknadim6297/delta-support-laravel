<?php

namespace App\Http\Middleware;

use App\Services\MemberPreviewGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds an isolated member-preview cookie onto the request attributes.
 * Does not alter Auth::user() or the primary web session member_context_id.
 */
class BindMemberPreviewSession
{
    public function __construct(private readonly MemberPreviewGuard $preview)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->preview->bindFromRequest($request);

        return $next($request);
    }
}
