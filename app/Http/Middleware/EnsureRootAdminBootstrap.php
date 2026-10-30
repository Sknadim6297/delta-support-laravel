<?php

namespace App\Http\Middleware;

use App\Services\ProductionDatabaseTarget;
use App\Services\RootAdminBootstrap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureRootAdminBootstrap
{
    public function __construct(
        private readonly RootAdminBootstrap $bootstrap,
        private readonly ProductionDatabaseTarget $targets,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('admin.bootstrap.create', 'admin.bootstrap.store')) {
            return $next($request);
        }

        if (! $request->secure() || $request->getQueryString() !== null) {
            abort(404);
        }

        if ($this->bootstrap->isConsumed() || ! $this->bootstrap->gateMatches((string) $request->route('gate'))) {
            abort(404);
        }

        $seal = $this->bootstrap->readSeal();
        $connected = (string) DB::connection()->getDatabaseName();
        $approved = (string) config('deltasupport.admin_bootstrap_database');
        if (
            $seal === null
            || $connected === ''
            || $approved === ''
            || $connected !== $approved
            || $connected !== $seal['database_name']
            || $this->targets->isProtected($connected)
        ) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
