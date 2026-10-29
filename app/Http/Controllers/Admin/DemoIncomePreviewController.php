<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoMemberSetup;
use App\Services\PackageIncomeAccrual;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DemoIncomePreviewController extends Controller
{
    public function index(DemoMemberSetup $setup, PackageIncomeAccrual $accrual): View
    {
        $date = CarbonImmutable::now('Asia/Kolkata')->toDateString();
        $purchaseIds = $setup->purchaseIds();
        $preview = [
            'date' => $date,
            'roi' => [],
            'commissions' => [],
            'skipped' => [],
        ];
        if ($purchaseIds !== []) {
            $preview = $accrual->previewPurchases($date, $purchaseIds);
        }

        return view('admin.demo.income', [
            'tree' => $setup->tree(),
            'preview' => $preview,
        ]);
    }
}
