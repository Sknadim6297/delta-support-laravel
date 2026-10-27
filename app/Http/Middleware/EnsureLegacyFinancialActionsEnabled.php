<?php

namespace App\Http\Middleware;

use App\Support\LegacyFinancialActions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegacyFinancialActionsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(LegacyFinancialActions::enabled(), 403, LegacyFinancialActions::DISABLED_MESSAGE);

        return $next($request);
    }
}
