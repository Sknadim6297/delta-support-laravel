<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\SupportNotification;
use App\Models\WithdrawalSetting;
use App\Services\ProductionDatabaseCommandGuard;
use App\Services\WithdrawalBalances;
use App\Support\MemberContext;
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
            $memberId = (string) (MemberContext::id($request) ?: 'guest');

            return [
                Limit::perMinutes(15, 3)->by('member:'.$memberId),
                Limit::perMinutes(15, 30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('member-pin-verify', function (Request $request) {
            $memberId = (string) (MemberContext::id($request) ?: 'guest');

            return Limit::perMinute(10)->by('member:'.$memberId);
        });

        RateLimiter::for('member-transfer', function (Request $request) {
            $memberId = (string) (MemberContext::id($request) ?: 'guest');

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
                $memberId = (string) (MemberContext::id($request) ?: '');
                $actor = $memberId !== '' ? 'member:'.$memberId : 'admin:'.($user->id ?? 'guest');
            }

            return [
                Limit::perMinute(6)->by($actor),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('member-transfer-lookup', function (Request $request) {
            $memberId = (string) (MemberContext::id($request) ?: 'guest');

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
            $view->with('headerBalance', self::headerWithdrawableBalance());
        });

        View::composer('member.layouts.app', function ($view): void {
            if (array_key_exists('member', $view->getData())) {
                return;
            }

            $memberId = MemberContext::id();
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
        $memberId = MemberContext::id();
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

    /**
     * Header total for wallets that can be withdrawn.
     * Member panel: that member's available total.
     * Admin panel: platform-wide available book.
     *
     * @return array{label: string, amount: string, hint: string}|null
     */
    private static function headerWithdrawableBalance(): ?array
    {
        if (! Schema::hasTable('members')) {
            return null;
        }

        $wallets = self::headerWithdrawableWallets();
        $balances = app(WithdrawalBalances::class);
        $adminScreen = request()->is('admin/*') || request()->routeIs('dashboard');

        if (! $adminScreen) {
            $memberId = MemberContext::id();
            if (! is_string($memberId) || $memberId === '') {
                return null;
            }
            $member = Member::query()->where('member_id', $memberId)->first();
            if ($member === null) {
                return null;
            }

            return [
                'label' => 'Total Balance',
                'amount' => $balances->totalAvailable($member, $wallets),
                'hint' => 'Sum of available balances in wallets that can be withdrawn',
            ];
        }

        if (auth()->user()?->is_admin !== true) {
            return null;
        }

        return [
            'label' => 'Total Balance',
            'amount' => $balances->book()['available'],
            'hint' => 'Platform total available across withdrawable wallets',
        ];
    }

    /**
     * @return list<string>
     */
    private static function headerWithdrawableWallets(): array
    {
        if (! Schema::hasTable('withdrawal_settings')) {
            return WithdrawalBalances::WALLETS;
        }

        $configured = WithdrawalSetting::current()->configuredWallets();

        return $configured !== [] ? $configured : WithdrawalBalances::WALLETS;
    }
}
