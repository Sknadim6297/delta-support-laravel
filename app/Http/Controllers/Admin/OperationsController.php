<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageIncomeTerm;
use App\Models\PackagePurchase;
use App\Models\RoiTransaction;
use App\Services\CommissionPlan;
use App\Services\FinancialHistoryQuery;
use App\Services\FinancialScheduleRegistrar;
use App\Services\ReferralLevelEligibility;
use App\Services\WalletBalanceReconciliation;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function walletOverview(Request $request): View
    {
        $members = $this->memberSearch($request)
            ->orderBy('member_id')
            ->paginate(20, ['member_id', 'member_name', 'status', 'roi_wallet_amount', 'working_wallet_amount', 'activation_wallet_amount'])
            ->withQueryString();

        return view('admin.wallets.overview', [
            'members' => $members,
            'totals' => $this->walletTotals(),
            'roiCount' => RoiTransaction::query()->count(),
            'levelCount' => LevelCommissionTransaction::query()->count(),
            'notices' => app(WalletBalanceReconciliation::class)->notices(),
        ]);
    }

    public function wallet(Request $request, string $wallet): View
    {
        abort_unless(in_array($wallet, ['roi', 'working', 'activation'], true), 404);
        $column = match ($wallet) {
            'roi' => 'roi_wallet_amount',
            'working' => 'working_wallet_amount',
            default => 'activation_wallet_amount',
        };
        $members = $this->memberSearch($request)
            ->orderByDesc($column)
            ->paginate(20, ['member_id', 'member_name', 'status', $column])
            ->withQueryString();

        return view('admin.wallets.index', [
            'wallet' => $wallet,
            'column' => $column,
            'members' => $members,
        ]);
    }

    public function roiHistory(Request $request, FinancialHistoryQuery $history): View
    {
        return view('admin.finance.history', [
            'kind' => 'roi',
            'rows' => $history->roi(null, (string) $request->query('q', ''), 15),
        ]);
    }

    public function levelIncomeHistory(Request $request, FinancialHistoryQuery $history): View
    {
        return view('admin.finance.history', [
            'kind' => 'level',
            'rows' => $history->levelIncome(null, (string) $request->query('q', ''), 15),
        ]);
    }

    public function eligibility(Request $request, ReferralLevelEligibility $eligibility): View
    {
        $members = $this->memberSearch($request)
            ->orderBy('member_id')
            ->paginate(20, ['member_id', 'member_name', 'status'])
            ->withQueryString();
        $summaries = $eligibility->summariesForMemberIds($members->pluck('member_id')->all());

        return view('admin.finance.eligibility', [
            'members' => $members,
            'summaries' => $summaries,
        ]);
    }

    public function walletTransactions(): View
    {
        return view('admin.wallets.transactions');
    }

    public function tours(): RedirectResponse
    {
        return redirect()->route('admin.packages.index');
    }

    public function roiPlans(): View
    {
        return view('admin.finance.roi-plans', [
            'packages' => Package::query()->orderBy('sort_order')->orderBy('name')->get(),
            'incomeTermCount' => PackageIncomeTerm::query()->count(),
        ]);
    }

    public function roiSchedule(FinancialScheduleRegistrar $registrar): View
    {
        $schedule = new Schedule;
        $registrar->register($schedule);

        return view('admin.finance.roi-schedule', [
            'scheduledTasks' => count($schedule->events()),
            'roiCount' => RoiTransaction::query()->count(),
            'legacyEnabled' => (bool) config('deltasupport.legacy_financial_actions'),
            'accelerated' => (bool) config('financial.accelerated.enabled'),
        ]);
    }

    public function commissionSettings(CommissionPlan $plan): View
    {
        $levels = $plan->levels();
        $total = '0.0000';
        foreach ($levels as $level) {
            $total = bcadd($total, (string) $level->percentage, 4);
        }

        return view('admin.finance.commission-settings', [
            'levels' => $levels,
            'total' => $total,
            'active' => $plan->isActive(),
            'errors' => $plan->integrityErrors(),
        ]);
    }

    public function levelUnlockRules(ReferralLevelEligibility $eligibility): View
    {
        $rules = [];
        foreach ([0, 1, 2, 3, 4, 5] as $count) {
            $rules[] = [
                'directs' => $count === 5 ? '5 or more' : (string) $count,
                'levels' => $eligibility->levelsForCount($count),
            ];
        }

        return view('admin.finance.level-rules', [
            'rules' => $rules,
            'qualifyingTypes' => array_map(
                fn (string $type): string => Package::TYPES[$type] ?? $type,
                ReferralLevelEligibility::QUALIFYING_PACKAGE_TYPES
            ),
        ]);
    }

    public function reports(string $report): View
    {
        abort_unless(in_array($report, ['members', 'packages', 'payments', 'earnings'], true), 404);

        return view('admin.reports.catalog', [
            'report' => $report,
            'memberCount' => Member::query()->count(),
            'packageCount' => Package::query()->count(),
            'pendingPayments' => PackagePurchase::query()->where('status', PackagePurchase::PENDING)->count(),
            'approvedPayments' => PackagePurchase::query()->where('status', PackagePurchase::APPROVED)->count(),
            'roiCount' => RoiTransaction::query()->count(),
            'levelCount' => LevelCommissionTransaction::query()->count(),
        ]);
    }

    private function memberSearch(Request $request): Builder
    {
        $search = trim((string) $request->query('q', ''));
        $query = Member::query();
        if ($search === '') {
            return $query;
        }

        $like = '%'.addcslashes($search, '\\%_').'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('member_id', 'like', $like)
                ->orWhere('member_name', 'like', $like);
        });
    }

    /**
     * @return array{roi: string, working: string, activation: string}
     */
    private function walletTotals(): array
    {
        $totals = Member::query()
            ->selectRaw('COALESCE(SUM(roi_wallet_amount), 0) as roi_total, COALESCE(SUM(working_wallet_amount), 0) as working_total, COALESCE(SUM(activation_wallet_amount), 0) as activation_total')
            ->first();

        return [
            'roi' => bcadd((string) ($totals->roi_total ?? '0'), '0', 4),
            'working' => bcadd((string) ($totals->working_total ?? '0'), '0', 4),
            'activation' => bcadd((string) ($totals->activation_total ?? '0'), '0', 4),
        ];
    }
}
