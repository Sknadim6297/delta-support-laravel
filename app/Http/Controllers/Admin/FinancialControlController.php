<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\FinancialControl;
use App\Models\PackageIncomeRun;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Services\FinancialControls;
use App\Services\WalletBalanceReconciliation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class FinancialControlController extends Controller
{
    public function edit(FinancialControls $controls, WalletBalanceReconciliation $reconciliation): View
    {
        $timezone = (string) config('deltasupport.package_income.timezone', 'Asia/Kolkata');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $last = PackageIncomeRun::query()->latest('id')->first();
        $rewardRun = Schema::hasTable('reward_evaluation_runs')
            ? DB::table('reward_evaluation_runs')->latest('id')->first()
            : null;

        return view('admin.settings.financial', [
            'armed' => $controls->automationArmed(),
            'stopped' => $controls->emergencyStopped(),
            'posting' => $controls->automaticPostingAllowed(),
            'timezone' => $timezone,
            'today' => $today,
            'last' => $last,
            'roiToday' => PackageRoiAccrual::query()->whereDate('business_date', $today)->count(),
            'commissionToday' => PackageLevelAccrual::query()->whereDate('business_date', $today)->count(),
            'rewardRun' => $rewardRun,
            'negative' => $reconciliation->negativeBalanceCount(),
            'failedJobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'withdrawals' => (bool) FinancialControl::current()->withdrawal_requests_enabled,
            'payouts' => (bool) FinancialControl::current()->external_payouts_enabled,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $row = FinancialControl::current();
        $row->financial_emergency_stop = $request->boolean('financial_emergency_stop');
        $row->save();

        AdminActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $row->financial_emergency_stop ? 'financial_emergency_stop' : 'financial_emergency_released',
            'subject' => 'financial_controls',
            'format' => null,
            'scope' => 'admin',
            'filters' => ['stopped' => (bool) $row->financial_emergency_stop],
            'row_count' => 1,
        ]);

        return redirect()
            ->route('admin.settings.financial')
            ->with('status', $row->financial_emergency_stop
                ? 'Emergency stop is on. No automatic wallet credit will be posted.'
                : 'Emergency stop is off. Automatic posting runs only when it is armed.');
    }
}
