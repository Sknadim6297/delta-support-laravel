<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\SupportNotification;
use App\Services\ProductionDatabaseCommandGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        RateLimiter::for('admin-bootstrap', function (Request $request) {
            return Limit::perMinute(5)->by('admin-bootstrap:'.$request->ip());
        });

        RateLimiter::for('member-pin-otp', function (Request $request) {
            $memberId = (string) $request->session()->get('member_context_id', 'guest');

            return [
                Limit::perMinutes(15, 3)->by('member:'.$memberId),
                Limit::perMinutes(15, 30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('member-pin-verify', function (Request $request) {
            $memberId = (string) $request->session()->get('member_context_id', 'guest');

            return Limit::perMinute(10)->by('member:'.$memberId);
        });

        RateLimiter::for('member-transfer', function (Request $request) {
            $memberId = (string) $request->session()->get('member_context_id', 'guest');

            return [
                Limit::perMinute(6)->by('member:'.$memberId),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('support-ticket', function (Request $request) {
            $user = $request->user();
            if ($user && $request->routeIs('admin.support.*')) {
                $actor = 'admin:'.$user->id;
            } else {
                $memberId = (string) $request->session()->get('member_context_id', '');
                $actor = $memberId !== '' ? 'member:'.$memberId : 'admin:'.($user->id ?? 'guest');
            }

            return [
                Limit::perMinute(6)->by($actor),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('member-transfer-lookup', function (Request $request) {
            $memberId = (string) $request->session()->get('member_context_id', 'guest');

            return [
                Limit::perMinute(30)->by('member:'.$memberId),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
        });

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            app(ProductionDatabaseCommandGuard::class)->handle($event);
        });

        View::composer('partials.lux-bar', function ($view): void {
            $view->with('supportNotices', self::supportNotices());
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

    /**
     * @return \Illuminate\Support\Collection<int, SupportNotification>
     */
    private static function supportNotices()
    {
        if (! Schema::hasTable('support_notifications')) {
            return collect();
        }

        $adminScreen = request()->is('admin/*') || request()->routeIs('dashboard');
        $memberId = session('member_context_id');
        if (! $adminScreen && is_string($memberId) && $memberId !== '') {
            return SupportNotification::query()
                ->where('audience', SupportNotification::AUDIENCE_MEMBER)
                ->where('member_id', $memberId)
                ->whereNull('read_at')
                ->latest('id')
                ->limit(8)
                ->get();
        }

        $user = auth()->user();
        if ($user?->is_admin === true) {
            return SupportNotification::query()
                ->where('audience', SupportNotification::AUDIENCE_ADMIN)
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id)->orWhereNull('user_id');
                })
                ->whereNull('read_at')
                ->latest('id')
                ->limit(8)
                ->get();
        }

        return collect();
    }
}
