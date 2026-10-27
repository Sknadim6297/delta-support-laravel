<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyFinancialActionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('deltasupport.legacy_financial_actions'), 403, 'This financial action is disabled.');

        return $next($request);
    }
}
