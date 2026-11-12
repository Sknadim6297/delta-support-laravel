<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Read-only inspection of posted ROI and level-commission days.
 * Never writes wallets, accruals, controls, or ledger rows.
 */
class PostedFinancialCycleAudit
{
    /**
     * @param  list<string>  $businessDates
     * @return array{
     *   database: string,
     *   dates: list<array<string, mixed>>,
     *   rates: list<array{level: int, percentage: string, is_active: int, expected: string, matches: bool}>,
     *   package_income: array{DST-ROI-002: array<string, mixed>|null, DST-TOUR-003: mixed},
     *   unique_indexes: array{roi: bool, level: bool, ledger_reference: bool, ledger_idempotency: bool}
     * }
     */
    public function inspect(array $businessDates): array
    {
        foreach ($businessDates as $date) {
            if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Business dates must be YYYY-MM-DD. No change was made.');
            }
        }

        foreach (['package_roi_accruals', 'package_level_accruals', 'wallet_ledger_entries', 'package_income_runs', 'commission_plan_levels'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException($table.' is missing. No change was made.');
            }
        }

        $dates = [];
        foreach ($businessDates as $date) {
            $dates[] = $this->inspectDate($date);
        }

        return [
            'database' => (string) DB::connection()->getDatabaseName(),
            'dates' => $dates,
            'rates' => $this->rates(),
            'package_income' => [
                'DST-ROI-002' => config('deltasupport.package_income.packages.DST-ROI-002'),
                'DST-TOUR-003' => config('deltasupport.package_income.packages.DST-TOUR-003'),
            ],
            'unique_indexes' => [
                'roi' => $this->hasUniqueIndex('package_roi_accruals', 'package_roi_purchase_date_unique'),
                'level' => $this->hasUniqueIndex('package_level_accruals', 'package_level_purchase_member_date_unique'),
                'ledger_reference' => $this->hasUniqueIndex('wallet_ledger_entries', 'wallet_ledger_entries_reference_unique'),
                'ledger_idempotency' => $this->hasUniqueIndex('wallet_ledger_entries', 'wallet_ledger_entries_idempotency_key_unique'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectDate(string $date): array
    {
        $ymd = str_replace('-', '', $date);
        $runs = DB::table('package_income_runs')
            ->whereDate('business_date', $date)
            ->orderBy('id')
            ->get(['id', 'status', 'credited_roi', 'credited_commissions', 'message', 'created_at']);

        $roi = DB::table('package_roi_accruals')->whereDate('business_date', $date);
        $level = DB::table('package_level_accruals')->whereDate('business_date', $date);

        $roiRows = (clone $roi)->get(['reference', 'amount', 'status', 'member_id', 'package_purchase_id']);
        $levelRows = (clone $level)->get(['reference', 'amount', 'status', 'member_id', 'package_purchase_id', 'level', 'rate_percentage']);

        $roiMissingLedger = $roiRows
            ->where('status', 'accrued')
            ->filter(fn ($row): bool => ! DB::table('wallet_ledger_entries')->where('reference', $row->reference)->exists())
            ->values()
            ->all();
        $levelMissingLedger = $levelRows
            ->where('status', 'accrued')
            ->filter(fn ($row): bool => ! DB::table('wallet_ledger_entries')->where('reference', $row->reference)->exists())
            ->values()
            ->all();

        $dupRoi = DB::table('package_roi_accruals')
            ->select('package_purchase_id', DB::raw('COUNT(*) as c'))
            ->whereDate('business_date', $date)
            ->groupBy('package_purchase_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $dupLevel = DB::table('package_level_accruals')
            ->select('package_purchase_id', 'member_id', DB::raw('COUNT(*) as c'))
            ->whereDate('business_date', $date)
            ->groupBy('package_purchase_id', 'member_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $dupLedger = DB::table('wallet_ledger_entries')
            ->select('reference', DB::raw('COUNT(*) as c'))
            ->where('reference', 'like', '%-'.$ymd)
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $ledgerRoi = DB::table('wallet_ledger_entries')
            ->where('wallet', 'roi')
            ->where('direction', 'credit')
            ->where('reference', 'like', 'PROI-%-'.$ymd);
        $ledgerLevel = DB::table('wallet_ledger_entries')
            ->where('wallet', 'working')
            ->where('direction', 'credit')
            ->where('reference', 'like', 'PLVL-%-'.$ymd);

        $memberIds = $roiRows->pluck('member_id')
            ->merge($levelRows->pluck('member_id'))
            ->unique()
            ->values()
            ->all();
        $walletVariances = [];
        foreach ($memberIds as $memberId) {
            $member = DB::table('members')->where('member_id', $memberId)->first();
            if ($member === null) {
                continue;
            }
            $roiLedger = (string) (DB::table('wallet_ledger_entries')
                ->where('member_id', $memberId)
                ->where('wallet', 'roi')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) as total")
                ->value('total') ?? '0');
            $workingLedger = (string) (DB::table('wallet_ledger_entries')
                ->where('member_id', $memberId)
                ->where('wallet', 'working')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction='credit' THEN amount ELSE -amount END),0) as total")
                ->value('total') ?? '0');
            if (bccomp((string) $member->roi_wallet_amount, bcadd($roiLedger, '0', 4), 4) !== 0
                || bccomp((string) $member->working_wallet_amount, bcadd($workingLedger, '0', 4), 4) !== 0) {
                $walletVariances[] = [
                    'member_id' => $memberId,
                    'roi_wallet' => (string) $member->roi_wallet_amount,
                    'roi_ledger' => bcadd($roiLedger, '0', 4),
                    'working_wallet' => (string) $member->working_wallet_amount,
                    'working_ledger' => bcadd($workingLedger, '0', 4),
                ];
            }
        }

        return [
            'business_date' => $date,
            'runs' => $runs->map(fn ($run): array => (array) $run)->all(),
            'roi' => [
                'rows' => $roiRows->count(),
                'accrued' => $roiRows->where('status', 'accrued')->count(),
                'calculated' => $roiRows->where('status', 'calculated')->count(),
                'amount' => $this->sumAmounts($roiRows),
                'ledger_rows' => (clone $ledgerRoi)->count(),
                'ledger_amount' => bcadd((string) ((clone $ledgerRoi)->sum('amount') ?? '0'), '0', 4),
                'missing_ledger' => count($roiMissingLedger),
                'duplicate_keys' => $dupRoi->count(),
            ],
            'commissions' => [
                'rows' => $levelRows->count(),
                'accrued' => $levelRows->where('status', 'accrued')->count(),
                'calculated' => $levelRows->where('status', 'calculated')->count(),
                'amount' => $this->sumAmounts($levelRows),
                'ledger_rows' => (clone $ledgerLevel)->count(),
                'ledger_amount' => bcadd((string) ((clone $ledgerLevel)->sum('amount') ?? '0'), '0', 4),
                'missing_ledger' => count($levelMissingLedger),
                'duplicate_keys' => $dupLevel->count(),
            ],
            'duplicate_ledger_references' => $dupLedger->count(),
            'wallet_variances' => $walletVariances,
            'ok' => $roiMissingLedger === []
                && $levelMissingLedger === []
                && $dupRoi->isEmpty()
                && $dupLevel->isEmpty()
                && $dupLedger->isEmpty()
                && $walletVariances === []
                && $roiRows->where('status', 'calculated')->isEmpty()
                && $levelRows->where('status', 'calculated')->isEmpty(),
        ];
    }

    /**
     * @return list<array{level: int, percentage: string, is_active: int, expected: string, matches: bool}>
     */
    private function rates(): array
    {
        $rows = DB::table('commission_plan_levels')->orderBy('level')->get();
        $out = [];
        foreach (CommissionPlan::RATE_BY_LEVEL as $level => $expected) {
            $row = $rows->firstWhere('level', $level);
            $percentage = $row ? bcadd((string) $row->percentage, '0', 4) : '';
            $out[] = [
                'level' => $level,
                'percentage' => $percentage,
                'is_active' => $row ? (int) $row->is_active : -1,
                'expected' => $expected,
                'matches' => $row !== null && bccomp($percentage, $expected, 4) === 0 && (int) $row->is_active === 0,
            ];
        }

        return $out;
    }

    private function hasUniqueIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $name && ($index['unique'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function sumAmounts($rows): string
    {
        $total = '0.0000';
        foreach ($rows as $row) {
            $total = bcadd($total, (string) $row->amount, 4);
        }

        return $total;
    }
}
