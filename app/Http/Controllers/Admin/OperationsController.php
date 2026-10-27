<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function wallet(string $wallet): View
    {
        abort_unless(in_array($wallet, ['roi', 'working'], true), 404);
        $column = $wallet === 'roi' ? 'roi_wallet_amount' : 'working_wallet_amount';
        $members = Member::query()
            ->orderByDesc($column)
            ->paginate(20, ['member_id', 'member_name', 'status', $column]);

        return view('admin.wallets.index', [
            'wallet' => $wallet,
            'column' => $column,
            'members' => $members,
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

    public function reports(string $report): View
    {
        abort_unless(in_array($report, ['members', 'packages', 'payments', 'earnings'], true), 404);

        return view('admin.reports.catalog', [
            'report' => $report,
            'memberCount' => Member::query()->count(),
            'packageCount' => Package::query()->count(),
            'pendingPayments' => PackagePurchase::query()->where('status', PackagePurchase::PENDING)->count(),
            'approvedPayments' => PackagePurchase::query()->where('status', PackagePurchase::APPROVED)->count(),
        ]);
    }
}
