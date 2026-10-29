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
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'pin',
            'pin_confirmation',
            'current_pin',
            'security_pin',
            'otp',
            'pin_digits',
            'pin_confirmation_digits',
            'current_pin_digits',
            'security_pin_digits',
            'otp_digits',
        ]);
    })->create();
