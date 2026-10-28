<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureLegacyFinancialActionsEnabled;
use App\Http\Middleware\EnsureMemberContext;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\PreventImpersonatedMutations;
use App\Http\Middleware\RedirectImpersonatingAdmins;
use App\Http\Middleware\RequireAdminPasswordChange;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.password' => RequireAdminPasswordChange::class,
            'member.context' => EnsureMemberContext::class,
            'legacy.financial' => EnsureLegacyFinancialActionsEnabled::class,
            'impersonation.block' => RedirectImpersonatingAdmins::class,
        ]);
        $middleware->web(append: [
            PreventImpersonatedMutations::class,
        ]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
