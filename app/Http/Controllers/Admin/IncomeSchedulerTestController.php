<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PackageIncomeHistoryPreview;
use App\Services\PackageIncomeReadiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomeSchedulerTestController extends Controller
{
    public function __construct(
        private readonly PackageIncomeReadiness $readiness,
        private readonly PackageIncomeHistoryPreview $history,
    ) {
    }

    public function index(): View
    {
        $this->guard();

        return $this->page($this->readiness->report(PackageIncomeReadiness::SAMPLE_MEMBER_IDS), null);
    }

    public function preview(Request $request): View
    {
        $this->guard();
        $action = $request->validate([
            'preview' => ['required', 'in:roi,level,isolated'],
        ])['preview'];

        $before = $this->readiness->ledger();
        $report = $this->readiness->report(PackageIncomeReadiness::SAMPLE_MEMBER_IDS);
        $after = $this->readiness->ledger();
        abort_unless($before === $after, 409, 'The income preview changed stored financial records.');

        $message = match ($action) {
            'roi' => 'ROI preview finished. No wallet was credited and no income rows were written.',
            'level' => 'Level commission preview finished. No wallet was credited and no income rows were written.',
            default => 'Blocked check finished. No payout database was opened and no income rows were written.',
        };

        return $this->page($report, $message);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function page(array $report, ?string $message): View
    {
        return view('admin.finance.income-scheduler-test', [
            'report' => $report,
            'message' => $message,
            'history' => $this->history->rows(),
        ]);
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }
}
