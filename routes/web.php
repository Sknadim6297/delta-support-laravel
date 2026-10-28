<?php

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;

    use App\Http\Controllers\MemberController;
    use App\Http\Controllers\InvestmentController;
    use App\Http\Controllers\ActivationWalletController;
    use App\Http\Controllers\ChangePasswordController;
    use App\Http\Controllers\genealogyController;
    use App\Http\Controllers\SupportController;
    use App\Http\Controllers\ReportController;
    use App\Http\Controllers\RankController;
    use App\Http\Controllers\AdminComingSoonController;
    use App\Http\Controllers\Admin\RewardCampaignController;
    use App\Http\Controllers\Admin\RewardEligibilityController;
    use App\Http\Controllers\Admin\RewardReportController;
    use App\Http\Controllers\Member\RewardController as MemberRewardController;
    use App\Http\Controllers\Admin\CompanySettingController;
    use App\Http\Controllers\Admin\InvoiceController;
    use App\Http\Controllers\Admin\MemberDirectoryController;
    use App\Http\Controllers\Admin\MemberImpersonationController;
    use App\Services\MemberImpersonation;
    use App\Http\Controllers\Admin\OperationsController;
    use App\Http\Controllers\Admin\PackageController;
    use App\Http\Controllers\Admin\PaymentReviewController;
    use App\Http\Controllers\Admin\WithdrawalReviewController;
    use App\Http\Controllers\Admin\WithdrawalSettingController;
    use App\Http\Controllers\Member\CatalogController;
    use App\Http\Controllers\Member\NewMemberRegistrationController;
    use App\Http\Controllers\Member\ProfileController;
    use App\Http\Controllers\Member\TeamController;
    use App\Http\Controllers\Member\InvestController;
    use App\Http\Controllers\Member\MemberChangePasswordController;
    use App\Http\Controllers\Member\ComingSoonController;
    use App\Http\Controllers\Member\WithdrawalController;
    use App\Http\Controllers\Member\WalletController;
    use App\Http\Controllers\Member\RankController as MemberRankController;
    use App\Http\Controllers\Member\ReportController as MemberReportController;
    use App\Http\Controllers\Member\GenealogyController as MemberGenealogyController;
    use App\Http\Controllers\PublicMemberAuthController;
    use App\Models\Member;
    use App\Models\User;


    Route::get('/branding/logo', [CompanySettingController::class, 'logo'])->name('brand.logo');

    Route::get('/', function () {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        if (session()->has('member_context_id')) {
            return redirect()->route('member.dashboard');
        }

        return view('public.home');
    })->name('home');

    Route::view('/about', 'public.about')->name('public.about');
    Route::get('/packages', function () {
        $packages = \App\Models\Package::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('public.packages', compact('packages'));
    })->name('public.packages');
    Route::get('/packages/{package}/image', [PackageController::class, 'publicImage'])->name('public.packages.image');
    Route::view('/business-plan', 'public.business-plan')->name('public.business-plan');
    Route::view('/contact', 'public.contact')->name('public.contact');

    Route::get('/login', function () {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        if (session()->has('member_context_id')) {
            return redirect()->route('member.dashboard');
        }

        return view('public.auth.login');
    })->name('public.login');


    /*
    |--------------------------------------------------------------------------
    | Admin Login
    |--------------------------------------------------------------------------
    */

    Route::get('/admin/login', function () {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        if (session()->has('member_context_id')) {
            return redirect()->route('member.dashboard');
        }

        return view('public.auth.login');
    })->name('login');

    Route::get('/register', [PublicMemberAuthController::class, 'register'])
        ->name('member.register');

    Route::post('/register', [PublicMemberAuthController::class, 'storeRegistration'])
        ->middleware('throttle:20,1')
        ->name('member.register.store');

    Route::get('/register/check-sponsor', [PublicMemberAuthController::class, 'checkSponsor'])
        ->middleware('throttle:30,1')
        ->name('member.register.check-sponsor');

    Route::get('/forgot-password', [PublicMemberAuthController::class, 'forgot'])
        ->name('member.forgot');

    Route::post('/forgot-password/send-otp', [PublicMemberAuthController::class, 'sendOtp'])
        ->middleware('throttle:3,10')
        ->name('member.forgot.send');

    Route::post('/forgot-password/resend-otp', [PublicMemberAuthController::class, 'resendOtp'])
        ->middleware('throttle:3,10')
        ->name('member.forgot.resend');

    Route::get('/forgot-password/verify', [PublicMemberAuthController::class, 'verifyForm'])
        ->name('member.forgot.verify');

    Route::post('/forgot-password/verify', [PublicMemberAuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,10')
        ->name('member.forgot.verify.store');

    Route::get('/forgot-password/reset', [PublicMemberAuthController::class, 'resetForm'])
        ->name('member.forgot.reset');

    Route::post('/forgot-password/reset', [PublicMemberAuthController::class, 'resetPassword'])
        ->name('member.forgot.reset.store');


    Route::post('/login', [PublicMemberAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('member.login.store');

    Route::post('/admin/login', [PublicMemberAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.submit');


    Route::post('/admin/impersonation/stop', [MemberImpersonationController::class, 'stop'])
        ->middleware(['auth', 'admin'])
        ->name('admin.impersonation.stop');

    /*
    |--------------------------------------------------------------------------
    | Authenticated Admin Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth', 'admin', 'admin.password', 'impersonation.block'])->group(function () {

        Route::get('/member-panel/{member_id}', [MemberController::class, 'memberPanel'])
            ->name('admin.member-panel');

        Route::controller(RankController::class)
            ->prefix('admin/ranks')
            ->name('admin.ranks.')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{rank}/edit', 'edit')->name('edit');
                Route::put('/{rank}', 'update')->name('update');
                Route::delete('/{rank}', 'destroy')->name('destroy');
            });

        Route::get('/admin/coming-soon/{feature}', [AdminComingSoonController::class, 'show'])
            ->where('feature', '[a-z0-9-]+')
            ->name('admin.coming-soon');

        Route::get('/admin/members', [MemberDirectoryController::class, 'index'])->name('admin.members.directory');
        Route::post('/admin/members/{member}/impersonate', [MemberImpersonationController::class, 'start'])->name('admin.members.impersonate');
        Route::patch('/admin/members/{memberId}/status', [MemberDirectoryController::class, 'updateStatus'])->name('admin.members.status');

        Route::controller(PackageController::class)->prefix('admin/packages')->name('admin.packages.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{package}/image', 'image')->name('image');
            Route::patch('/{package}/status', 'updateStatus')->name('status');
            Route::get('/{package}/edit', 'edit')->name('edit');
            Route::put('/{package}', 'update')->name('update');
        });

        Route::get('/admin/purchases', [PaymentReviewController::class, 'purchases'])->name('admin.purchases.index');
        Route::controller(PaymentReviewController::class)->prefix('admin/payments')->name('admin.payments.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/settings', 'settings')->name('settings');
            Route::put('/settings', 'updateSettings')->name('settings.update');
            Route::get('/qr', 'qr')->name('qr');
            Route::get('/{purchase}', 'show')->name('show');
            Route::post('/{purchase}/approve', 'approve')->name('approve');
            Route::post('/{purchase}/reject', 'reject')->name('reject');
        });

        Route::controller(WithdrawalReviewController::class)->prefix('admin/withdrawals')->name('admin.withdrawals.')->group(function () {
            Route::get('/', 'overview')->name('overview');
            Route::get('/requests', 'index')->name('index');
            Route::get('/payouts', 'payouts')->name('payouts');
            Route::get('/requests/{withdrawalRequest}', 'show')->name('show');
        });

        Route::controller(WithdrawalSettingController::class)->prefix('admin/withdrawals')->name('admin.withdrawals.')->group(function () {
            Route::get('/settings', 'edit')->name('settings');
            Route::put('/settings', 'update')->name('settings.update');
        });

        Route::controller(InvoiceController::class)->prefix('admin/invoices')->name('admin.invoices.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{invoice}/print', 'print')->name('print');
            Route::get('/{invoice}/pdf', 'pdf')->name('pdf');
            Route::get('/{invoice}', 'show')->name('show');
        });

        Route::controller(CompanySettingController::class)->prefix('admin/settings')->name('admin.settings.')->group(function () {
            Route::get('/general', 'edit')->name('general');
            Route::put('/general', 'update')->name('general.update');
            Route::get('/branding', 'branding')->name('branding');
            Route::post('/branding/logo', 'updateLogo')->name('branding.logo.update');
            Route::delete('/branding/logo', 'destroyLogo')->name('branding.logo.destroy');
            Route::put('/branding/colors', 'updateColors')->name('branding.colors.update');
            Route::delete('/branding/colors', 'resetColors')->name('branding.colors.reset');
            Route::get('/security', 'security')->name('security');
            Route::get('/profile', 'profile')->name('profile');
            Route::put('/profile', 'updateProfile')->middleware('throttle:6,1')->name('profile.update');
            Route::put('/profile/password', 'updatePassword')->middleware('throttle:6,1')->name('profile.password');
        });

        Route::controller(OperationsController::class)->group(function () {
            Route::get('/admin/wallets', 'walletOverview')->name('admin.wallets.overview');
            Route::get('/admin/wallets/transactions', 'walletTransactions')->name('admin.wallets.transactions');
            Route::get('/admin/finance/roi-history', 'roiHistory')->name('admin.finance.roi-history');
            Route::get('/admin/finance/roi-plans', 'roiPlans')->name('admin.finance.roi-plans');
            Route::get('/admin/finance/roi-schedule', 'roiSchedule')->name('admin.finance.roi-schedule');
            Route::get('/admin/finance/level-income', 'levelIncomeHistory')->name('admin.finance.level-income');
            Route::get('/admin/finance/commission-settings', 'commissionSettings')->name('admin.finance.commission-settings');
            Route::get('/admin/finance/level-unlock-rules', 'levelUnlockRules')->name('admin.finance.level-unlock-rules');
            Route::get('/admin/finance/eligibility', 'eligibility')->name('admin.finance.eligibility');
            Route::get('/admin/wallets/{wallet}', 'wallet')->where('wallet', 'roi|working|activation')->name('admin.wallets.show');
            Route::get('/admin/tours', 'tours')->name('admin.tours.index');
        });

        Route::get('/admin/catalog-reports/{report}/export', [\App\Http\Controllers\Admin\CatalogReportController::class, 'export'])
            ->where('report', 'members|packages|payments|earnings')
            ->middleware('throttle:10,1')
            ->name('admin.catalog-reports.export');
        Route::get('/admin/catalog-reports/members/{member}', [\App\Http\Controllers\Admin\CatalogReportController::class, 'member'])
            ->name('admin.catalog-reports.members.show');
        Route::get('/admin/catalog-reports/{report}', [\App\Http\Controllers\Admin\CatalogReportController::class, 'show'])
            ->where('report', 'members|packages|payments|earnings')
            ->name('admin.catalog-reports.show');

        Route::get('/admin/finance/income-test', [\App\Http\Controllers\Admin\IncomeSchedulerTestController::class, 'index'])->name('admin.finance.income-test');
        Route::post('/admin/finance/income-test', [\App\Http\Controllers\Admin\IncomeSchedulerTestController::class, 'preview'])->name('admin.finance.income-test.preview');

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', function (Request $request) {
            $request->session()->forget('member_context_id');

            $purchaseColumns = ['id', 'reference', 'member_id', 'package_name', 'price_usd', 'denomination', 'status', 'created_at', 'reviewed_at'];

            return view('admin.dashboard.index', [
                'activationWalletTotal' => (float) \App\Models\Member::sum('activation_wallet_amount'),
                'workingWalletTotal' => (float) \App\Models\Member::sum('working_wallet_amount'),
                'roiWalletTotal' => (float) \App\Models\Member::sum('roi_wallet_amount'),
                'memberCount' => \App\Models\Member::count(),
                'activeMembers' => \App\Models\Member::where('status', 'active')->count(),
                'inactiveMembers' => \App\Models\Member::where('status', 'inactive')->count(),
                'packageCount' => \App\Models\Package::count(),
                'activePackageCount' => \App\Models\Package::where('is_active', true)->count(),
                'approvedPurchases' => \App\Support\CommerceTotals::sums(\App\Models\PackagePurchase::class, \App\Models\PackagePurchase::APPROVED),
                'pendingPurchases' => \App\Support\CommerceTotals::sums(\App\Models\PackagePurchase::class, \App\Models\PackagePurchase::PENDING),
                'pendingCount' => \App\Models\PackagePurchase::where('status', \App\Models\PackagePurchase::PENDING)->count(),
                'approvedCount' => \App\Models\PackagePurchase::where('status', \App\Models\PackagePurchase::APPROVED)->count(),
                'rejectedCount' => \App\Models\PackagePurchase::where('status', \App\Models\PackagePurchase::REJECTED)->count(),
                'recentMembers' => \App\Models\Member::query()->latest()->limit(5)->get(['member_id', 'member_name', 'status', 'created_at']),
                'recentPurchases' => \App\Models\PackagePurchase::query()->latest()->limit(5)->get($purchaseColumns),
                'recentReviews' => \App\Models\PackagePurchase::query()->whereNotNull('reviewed_at')->latest('reviewed_at')->limit(5)->get($purchaseColumns),
                'rewardActive' => \App\Models\RewardCampaign::query()->where('status', 'active')->count(),
                'rewardQualified' => \App\Models\RewardQualification::query()->whereIn('status', ['qualified', 'selected'])->count(),
                'rewardPending' => \App\Models\RewardDelivery::query()->whereIn('status', ['pending', 'approved', 'processing'])->count(),
                'rewardDispatched' => \App\Models\RewardDelivery::query()->where('status', 'shipped')->count(),
                'rewardDelivered' => \App\Models\RewardDelivery::query()->where('status', 'delivered')->count(),
            ]);
        })->name('dashboard');


        /*
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        */

        Route::post('/admin/logout', function (Request $request) {
            if ($request->user()) {
                \App\Models\AdminActivityLog::query()->create([
                    'user_id' => $request->user()->id,
                    'action' => 'admin_logout',
                    'subject' => 'admin',
                    'format' => null,
                    'scope' => 'admin',
                    'filters' => [],
                    'row_count' => 1,
                ]);
            }
            app(MemberImpersonation::class)->finish($request);
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        })->name('admin.logout');


        /*
        |--------------------------------------------------------------------------
        | Members
        |--------------------------------------------------------------------------
        */

        Route::controller(MemberController::class)
            ->prefix('admin/members')
            ->name('admin.members.')
            ->group(function () {

                Route::get('/active', 'active')->name('active');

                Route::get('/inactive', 'inactive')->name('inactive');

                Route::get('/export/{status}', 'export')->name('export');

                Route::get('/registration', 'registration')->name('registration');

                Route::get('/update', 'update')->name('update');

                Route::get(
                    '/fetch-details',
                    'fetchMemberDetails'
                )->name('fetch-details');

                Route::get(
                    '/check-member-id',
                    'checkMemberIdAvailability'
                )->name('check-member-id');

                Route::get(
                    '/check-sponsor-id',
                    'checkSponsorIdAvailability'
                )->name('check-sponsor-id');

                Route::post('/store', 'store')->name('store');

                Route::post(
                    '/update-member',
                    'updateMember'
                )->name('update-member');
            });


        /*
        |--------------------------------------------------------------------------
        | Investments
        |--------------------------------------------------------------------------
        */

        Route::controller(InvestmentController::class)
            ->prefix('admin/investments')
            ->name('admin.investments.')
            ->group(function () {

                Route::get('/entry', 'investmentEntry')
                    ->name('entry');

                Route::get('/member-lookup', 'memberLookup')
                    ->name('member-lookup');

                Route::post('/store', 'storeInvestment')
                    ->middleware('legacy.financial')
                    ->name('store');

                Route::get(
                    '/investment-withdrawal-entry',
                    'investmentWithdrawalEntry'
                )->name('investment-withdrawal-entry');

                Route::get(
                    '/investment-withdrawal-lookup',
                    'investmentWithdrawalLookup'
                )->name('investment-withdrawal-lookup');

                Route::get(
                    '/investment-withdrawal-investment-lookup',
                    'investmentWithdrawalInvestmentLookup'
                )->name('investment-withdrawal-investment-lookup');

                Route::post(
                    '/investment-withdrawal-entry',
                    'storeInvestmentWithdrawal'
                )->middleware('legacy.financial')->name('investment-withdrawal-store');
            
                Route::get(
                    '/investment-withdrawal-list',
                    'investmentWithdrawalList'
                )->name('investment-withdrawal-list');


                Route::get(
                    '/active-investments',
                    'activeInvestments'
                )->name('active-investments');

                Route::get(
                    '/active-investments/export',
                    'exportActiveInvestments'
                )->name('active-investments.export');

                Route::get(
                    '/closed-investments/export',
                    'exportClosedInvestments'
                )->name('closed-investments.export');

                Route::get(
                    '/closed-investments',
                    'closedInvestments'
                )->name('closed-investments');
            });


        /*
        |--------------------------------------------------------------------------
        | Activation Wallet
        |--------------------------------------------------------------------------
        */

        Route::controller(ActivationWalletController::class)
            ->prefix('admin/activation-wallet')
            ->name('admin.activation-wallet.')
            ->group(function () {

                Route::get(
                    '/credit-entry',
                    'creditEntry'
                )->name('credit-entry');

                Route::get(
                    '/credit-entry/member-lookup',
                    'memberLookup'
                )->name('credit-entry.member-lookup');

                Route::post(
                    '/credit-entry',
                    'storeCreditEntry'
                )->middleware('legacy.financial')->name('store-credit-entry');

                Route::get(
                    '/credit-entry/export',
                    'exportCreditEntries'
                )->name('credit-entry.export');

                Route::get(
                    '/credit-entry/list',
                    'listCreditEntries'
                )->name('credit-entry.list');

                Route::get(
                    '/debit-entry',
                    'debitEntry'
                )->name('debit-entry');

                Route::post(
                    '/debit-entry',
                    'storeDebitEntry'
                )->middleware('legacy.financial')->name('store-debit-entry');

                Route::get(
                    '/debit-entry/list',
                    'listDebitEntries'
                )->name('debit-entry.list');

                Route::get(
                    '/summary/export',
                    'exportSummary'
                )->name('summary.export');

                Route::get(
                    '/summary',
                    'summary'
                )->name('summary');
            });


        /*
        |--------------------------------------------------------------------------
        | Change Password
        |--------------------------------------------------------------------------
        */

        Route::controller(ChangePasswordController::class)
            ->prefix('admin/change-password')
            ->name('admin.change-password.')
            ->group(function () {

                Route::get(
                    '/change-password',
                    'index'
                )->name('index');

                Route::post(
                    '/update-password',
                    'update'
                )->name('update');
            });


        /*
        |--------------------------------------------------------------------------
        | Genealogy
        |--------------------------------------------------------------------------
        */

        Route::controller(genealogyController::class)
            ->prefix('admin/genealogy')
            ->name('admin.genealogy.')
            ->group(function () {

                Route::get(
                    '/tree-view',
                    'index'
                )->name('tree-view');
                Route::get(
                    '/search-members',
                    'searchMembers'
                )->name('search-members');
                Route::get(
                    '/level-view',
                    'levelView'
                )->name('level-view');
                Route::get(
                    '/level-view/members',
                    'levelMembers'
                )->name('level-view.members');
            });


        /*
        |--------------------------------------------------------------------------
        | Support
        |--------------------------------------------------------------------------
        */

        Route::controller(SupportController::class)
            ->prefix('admin/support')
            ->name('admin.support.')
            ->group(function () {

                Route::get(
                    '/pending-tickets',
                    'pendingTickets'
                )->name('pending-tickets');

                Route::get(
                    '/applied-tickets',
                    'appliedTicketList'
                )->name('applied-tickets');

                Route::get(
                    '/applied-tickets/export',
                    'exportAppliedTickets'
                )->name('applied-tickets.export');
            });

            /* 
        |--------------------------------------------------------------------------
        | Report
        |--------------------------------------------------------------------------
        */

        Route::controller(ReportController::class)
            ->prefix('admin/report')
            ->name('admin.report.')
            ->group(function () {

                Route::get(
                    '/roi-report',
                    'roiReport'
                )->name('roi-report');
                Route::get(
                    '/roi-report/total',
                    'roiReportTotal'
                )->name('roi-report.total');
                Route::get(
                    '/roi-report/total-entries',
                    'roiReportEntries'
                )->name('roi-report.total-entries');
                Route::get(
                    '/roi-report/export',
                    'exportRoiReport'
                )->name('roi-report.export');
                Route::get(
                    '/level-income',
                    'levelIncomeReport'
                )->name('level-income');
                Route::get(
                    '/level-income/total',
                    'levelIncomeReportTotal'
                )->name('level-income.total');
                Route::get(
                    '/level-income/total-entries',
                    'levelIncomeReportEntries'
                )->name('level-income.total-entries');
                Route::get(
                    '/level-income/export',
                    'exportLevelIncomeReport'
                )->name('level-income.export');
                Route::get(
                    '/salary-report',
                    'salaryReport'
                )->name('salary');
                Route::get(
                    '/rank-achievement',
                    'rankAchievementReport'
                )->name('rank-achievement');
                
            });

        Route::controller(RewardCampaignController::class)->prefix('admin/rewards')->name('admin.rewards.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/eligible', [RewardEligibilityController::class, 'index'])->name('eligible');
            Route::get('/reports', [RewardReportController::class, 'index'])->name('reports');
            Route::get('/export', [RewardReportController::class, 'export'])->name('export');
            Route::get('/qualifications/{qualification}', [RewardEligibilityController::class, 'show'])->name('qualifications.show');
            Route::post('/qualifications/{qualification}/delivery', [RewardEligibilityController::class, 'updateDelivery'])->name('deliveries.update');
            Route::get('/{campaign}', 'show')->name('show');
            Route::get('/{campaign}/edit', 'edit')->name('edit');
            Route::put('/{campaign}', 'update')->name('update');
            Route::post('/{campaign}/publish', 'publish')->name('publish');
            Route::post('/{campaign}/pause', 'pause')->name('pause');
            Route::post('/{campaign}/close', 'close')->name('close');
            Route::post('/{campaign}/cancel', 'cancel')->name('cancel');
            Route::post('/{campaign}/evaluate', 'evaluate')->name('evaluate');
            Route::get('/{campaign}/image', 'image')->name('image');
        });
        
        
    });

    Route::middleware('member.context')->group(function () {
        Route::controller(MemberGenealogyController::class)
            ->prefix('member/genealogy')
            ->name('member.genealogy.')
            ->group(function () {
                Route::get('/tree-view', 'treeView')->name('tree-view');
                Route::get('/tree-view/children', 'treeChildren')->name('tree-children');
                Route::get('/level-view', 'levelView')->name('level-view');
                Route::get('/level-view/members', 'levelMembers')->name('level-view.members');
            });

        Route::get('/member/search-members', [MemberController::class, 'searchMembers'])
            ->name('member.search-members');

        Route::get('/member/lookup-member', [MemberController::class, 'lookupMember'])
            ->name('member.lookup-member');

        Route::get('/member/rank', [MemberRankController::class, 'show'])
            ->name('member.rank');

        Route::get('/member/rewards', [MemberRewardController::class, 'index'])->name('member.rewards.index');
        Route::get('/member/rewards/{campaign}/image', [MemberRewardController::class, 'image'])->name('member.rewards.image');

        Route::controller(CatalogController::class)->group(function () {
            Route::get('/member/packages', 'explore')->name('member.packages.explore');
            Route::get('/member/packages/mine', 'mine')->name('member.packages.mine');
            Route::get('/member/packages/{package}', 'show')->name('member.packages.show');
            Route::post('/member/packages/{package}/purchase', 'purchase')->name('member.packages.purchase');
            Route::get('/member/packages/{package}/image', 'packageImage')->name('member.packages.image');
            Route::get('/member/payments', 'payments')->name('member.payments.index');
            Route::get('/member/payments/qr', 'qr')->name('member.payments.qr');
            Route::get('/member/payments/{purchase}', 'payment')->name('member.payments.show');
            Route::get('/member/invoices', 'invoices')->name('member.invoices.index');
            Route::get('/member/invoices/{invoice}/print', 'invoicePrint')->name('member.invoices.print');
            Route::get('/member/invoices/{invoice}/pdf', 'invoicePdf')->name('member.invoices.pdf');
            Route::get('/member/invoices/{invoice}', 'invoice')->name('member.invoices.show');
            Route::get('/member/referral', 'referral')->name('member.referral');
            Route::get('/member/tours', 'tours')->name('member.tours');
            Route::get('/member/support', 'support')->name('member.support');
        });

        Route::controller(WalletController::class)->group(function () {
            Route::get('/member/wallets', 'overview')->name('member.wallets.overview');
            Route::get('/member/wallets/{wallet}', 'wallet')->where('wallet', 'roi|working')->name('member.wallets.show');
            Route::get('/member/income/roi', 'roiHistory')->name('member.income.roi');
            Route::get('/member/income/level', 'levelHistory')->name('member.income.level');
            Route::get('/member/eligibility', 'eligibility')->name('member.eligibility');
        });

        Route::controller(WithdrawalController::class)->prefix('member/withdrawals')->name('member.withdrawals.')->group(function () {
            Route::get('/', 'requestForm')->name('request');
            Route::get('/history', 'history')->name('history');
            Route::get('/{withdrawalRequest}', 'show')->name('show');
        });

        Route::get('/member/coming-soon/{feature}', [ComingSoonController::class, 'show'])
            ->where('feature', '[a-z0-9-]+')
            ->name('member.coming-soon');

        Route::controller(MemberReportController::class)
            ->prefix('member/reports')
            ->name('member.reports.')
            ->group(function () {
                Route::get('/roi', 'roiReport')->name('roi');
                Route::get('/roi/export', 'exportRoiReport')->name('roi.export');
                Route::get('/level-income', 'levelIncomeReport')->name('level-income');
                Route::get('/level-income/export', 'exportLevelIncomeReport')->name('level-income.export');
                Route::get('/rank-achievement', 'rankAchievementReport')->name('rank-achievement');
            });

        Route::controller(InvestController::class)
            ->prefix('member/investments')
            ->name('member.investments.')
            ->group(function () {
                Route::get('/entry', 'entry')->name('entry');
                Route::post('/entry', 'store')->middleware('legacy.financial')->name('store');
                Route::get('/active', 'active')->name('active');
                Route::get('/closed', 'closed')->name('closed');
            });

        Route::get('/member/profile', [ProfileController::class, 'show'])
            ->name('member.profile');

        Route::get('/member/profile/edit', [ProfileController::class, 'edit'])
            ->name('member.profile.edit');

        Route::put('/member/profile', [ProfileController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('member.profile.update');

        Route::get('/member/team/direct', [TeamController::class, 'direct'])
            ->name('member.team.direct');

        Route::get('/member/team', [TeamController::class, 'whole'])
            ->name('member.team.whole');

        Route::get('/member/registration', [NewMemberRegistrationController::class, 'create'])
            ->name('member.registration');

        Route::get('/member/registration/check-sponsor', [NewMemberRegistrationController::class, 'checkSponsor'])
            ->name('member.registration.check-sponsor');

        Route::post('/member/registration', [NewMemberRegistrationController::class, 'store'])
            ->name('member.registration.store');

        Route::get('/member/dashboard', function (Request $request) {
            $member = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();

            return app(MemberController::class)->memberPanel($member->member_id, $request);
        })->name('member.dashboard');

        
       Route::get('/member/change-password', [MemberChangePasswordController::class, 'index'])
        ->name('member.change-password');

       Route::post('/member/change-password', [MemberChangePasswordController::class, 'update'])
        ->name('member.change-password.post');

        Route::get('/member/change-password/update', [SupportController::class, 'memberUpdate'])->name('member.change-password.update');


        Route::post('/member/logout', function (Request $request) {
        app(MemberImpersonation::class)->finish($request);
        if ($request->user()?->is_admin === true) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    })->name('member.logout');
    });