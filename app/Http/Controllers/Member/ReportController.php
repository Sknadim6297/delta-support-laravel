<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Services\RankService;
use App\Services\ReportSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function roiReport(Request $request, ReportSummaryService $summaryService): View
    {
        $member = $this->currentMember($request);
        $query = $this->roiQuery($member, $request);
        $totalAmount = $summaryService->roiReportTotal(
            $member->member_id,
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) (clone $query)->sum('income_amount');
        $totalAmount = $this->formatDecimalAmount($totalAmount);

        return view('member.reports.roi-report', [
            'member' => $member,
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportRoiReport(Request $request): Response
    {
        $transactions = $this->roiQuery($this->currentMember($request), $request);

        return $this->csvResponse('roi-report-' . now()->format('Y-m-d') . '.csv', [
            'Serial No', 'Form Investment ID', 'Income Amount (USDT)', 'On Investment Amount (USDT)', 'Date',
        ], $transactions, function (RoiTransaction $transaction, int $index): array {
            return [
                $index + 1,
                $transaction->investment_id,
                $this->formatUsdt($transaction->income_amount),
                $this->formatUsdt($transaction->on_amount),
                $transaction->created_at?->format('d-M-Y'),
            ];
        });
    }

    public function levelIncomeReport(Request $request, RankService $rankService, ReportSummaryService $summaryService): View
    {
        $member = $this->currentMember($request);
        $query = $this->levelIncomeQuery($member, $request);
        $level = $request->filled('level') ? (int) $request->query('level') : null;
        $totalAmount = $summaryService->levelCommissionReportTotal(
            $member->member_id,
            $level,
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) (clone $query)->sum('income_amount');
        $totalAmount = $this->formatDecimalAmount($totalAmount);
        $levels = LevelCommissionTransaction::query()
            ->where('member_id', $member->member_id)
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');
        $rankData = $rankService->calculateForMember($member);
        $levelAccess = collect(range(1, 32))->map(fn (int $level): array => [
            'level' => $level,
            'unlocked' => $level <= $rankData['unlocked_levels'],
        ]);

        return view('member.reports.level-income', [
            'member' => $member,
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
            'levels' => $levels,
            'rankData' => $rankData,
            'levelAccess' => $levelAccess,
        ]);
    }

    public function exportLevelIncomeReport(Request $request): Response
    {
        $transactions = $this->levelIncomeQuery($this->currentMember($request), $request);

        return $this->csvResponse('level-commission-report-' . now()->format('Y-m-d') . '.csv', [
            'Serial No', 'Income Amount (USDT)', 'On Amount (USDT)', 'From Member ID', 'From Level', 'Date',
        ], $transactions, function (LevelCommissionTransaction $transaction, int $index): array {
            return [
                $index + 1,
                $this->formatUsdt($transaction->income_amount),
                $this->formatUsdt($transaction->on_amount),
                $transaction->from_member_id,
                $transaction->level,
                $transaction->created_at?->format('d-M-Y'),
            ];
        });
    }

    public function rankAchievementReport(Request $request, RankService $rankService): View
    {
        $member = $this->currentMember($request);
        $query = $this->rankAchievementQuery($member, $request);
        $rankData = $rankService->calculateForMember(
            $member,
            $request->query('from_date'),
            $request->query('to_date')
        );
        $ranks = \App\Models\Rank::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('member.reports.rank-achievement', [
            'member' => $member,
            'achievements' => $query->latest('achieved_at')->paginate(10)->withQueryString(),
            'totalAmount' => $rankData['full_team_business'],
            'ranks' => $ranks,
        ]);
    }

    private function roiQuery(Member $member, Request $request)
    {
        $query = RoiTransaction::query()
            ->where('member_id', $member->member_id)
            ->whereHas('investment', function ($investmentQuery) use ($member) {
                $investmentQuery->where('member_id', $member->member_id);
            });

        $this->applyDateFilters($query, $request);

        return $query;
    }

    private function levelIncomeQuery(Member $member, Request $request)
    {
        $query = LevelCommissionTransaction::query()
            ->where('member_id', $member->member_id)
            ->select(['id', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at']);

        $this->applyDateFilters($query, $request);

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        return $query;
    }

    private function rankAchievementQuery(Member $member, Request $request)
    {
        $query = RankAchievement::query()
            ->where('member_id', $member->member_id)
            ->with('rank');

        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            if ($memberId !== '') {
                $query->where('member_id', 'like', $memberId . '%');
            }
        }

        if ($request->filled('rank_id')) {
            $rankId = trim((string) $request->query('rank_id'));
            if ($rankId !== '') {
                $query->where('rank_id', $rankId);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('achieved_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('achieved_at', '<=', $request->query('to_date'));
        }

        return $query;
    }

    private function applyDateFilters($query, Request $request): void
    {
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }
    }

    private function dateKey(Request $request, string $field): ?int
    {
        return $request->filled($field)
            ? (int) CarbonImmutable::parse($request->query($field))->format('Ymd')
            : null;
    }

    private function currentMember(Request $request): Member
    {
        return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function csvResponse(string $filename, array $headers, $query, callable $rowFormatter): Response
    {
        $query = (clone $query)->latest('created_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($headers, $query, $rowFormatter) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $serial = 0;
            $lastCreatedAt = null;
            $lastId = null;

            do {
                $batch = (clone $query)
                    ->when($lastCreatedAt !== null, function ($batchQuery) use ($lastCreatedAt, $lastId): void {
                        $batchQuery->where(function ($cursor) use ($lastCreatedAt, $lastId): void {
                            $cursor->where('created_at', '<', $lastCreatedAt)
                                ->orWhere(function ($tie) use ($lastCreatedAt, $lastId): void {
                                    $tie->where('created_at', $lastCreatedAt)
                                        ->where('id', '<', $lastId);
                                });
                        });
                    })
                    ->limit(500)
                    ->get();

                foreach ($batch as $record) {
                    fputcsv($output, $rowFormatter($record, $serial));
                    $serial++;
                    $lastCreatedAt = $record->created_at;
                    $lastId = $record->id;
                }
            } while ($batch->count() === 500);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatUsdt($amount): string
    {
        return \App\Support\MoneyDisplay::usdt($amount);
    }

    private function formatDecimalAmount($amount): string
    {
        $formatted = bcadd((string) $amount, '0', 4);

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
