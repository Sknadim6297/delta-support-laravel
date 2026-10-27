<?php

namespace App\Providers;

use App\Models\Member;
use App\Services\ProductionDatabaseCommandGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            app(ProductionDatabaseCommandGuard::class)->handle($event);
        });

        View::composer('member.layouts.app', function ($view): void {
            if (array_key_exists('member', $view->getData())) {
                return;
            }

            $memberId = session('member_context_id');
            $member = $memberId ? Member::query()->where('member_id', $memberId)->first() : null;
            if ($member) {
                $view->with('member', $member);
            }
        });
    }
}
