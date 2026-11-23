<?php

namespace App\Http\Middleware;

use App\Models\Member;
use App\Support\MemberContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (MemberContext::isPreview($request)) {
            $memberId = MemberContext::id($request);
            abort_unless($memberId && Member::query()->where('member_id', $memberId)->exists(), 403);

            return $next($request);
        }

        if ($request->user()?->is_admin === true && ! $request->session()->has('member_context_id')) {
            return redirect()->route('dashboard');
        }

        $memberId = MemberContext::id($request);

        abort_unless($memberId && Member::query()->where('member_id', $memberId)->exists(), 403);

        return $next($request);
    }
}
