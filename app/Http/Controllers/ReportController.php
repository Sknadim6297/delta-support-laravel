<?php

namespace App\Http\Controllers;

use App\Http\Responses\ReadableStreamedResponse;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\RankAchievement;
use App\Models\RoiTransaction;
use App\Services\RankService;
use App\Services\ReportSummaryService;
use App\Support\QueryDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function roiReport(Request $request, ReportSummaryService $summaryService)
    {
        $query = $this->roiQuery($request);
        $totalAmount = $summaryService->roiReportTotal(
            $this->memberSearch($request),
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) (clone $query)->sum('income_amount');
        $totalAmount = $this->formatDecimalAmount($totalAmount);
        $transactions = $query->latest()->paginate(10)->withQueryString();

        return view('admin.reports.roi-report', [
            'transactions' => $transactions,
            'totalAmount' => $totalAmount,
        ]);
    }

    public function roiReportTotal(Request $request, ReportSummaryService $summaryService): JsonResponse
    {
        $request->validate([
            'member_id' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $totalAmount = $summaryService->roiReportTotal(
            $this->memberSearch($request),
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) $this->roiQuery($request)->sum('income_amount');

        return response()->json(['total_amount' => $this->formatDecimalAmount($totalAmount)]);
    }

    public function roiReportEntries(Request $request, ReportSummaryService $summaryService): JsonResponse
    {
        $request->validate([
            'member_id' => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $totalEntries = $summaryService->roiReportTransactionCount(
            $this->memberSearch($request),
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) $this->roiQuery($request)->count();

        return response()->json(['total_entries' => $totalEntries]);
    }

    public function exportRoiReport(Request $request)
    {
        $query = $this->roiQuery($request)->latest('created_at')->orderByDesc('id');

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Investment ID', 'Income Amount (USDT)', 'On Amount (USDT)', 'Date']);

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

                foreach ($batch as $transaction) {
                    fputcsv($output, [
                        ++$serial,
                        $transaction->member_id,
                        $transaction->member_name,
                        $transaction->investment_id,
                        $this->formatUsdt($transaction->income_amount),
                        $this->formatUsdt($transaction->on_amount),
                        $transaction->created_at?->format('d-M-Y'),
                    ]);
                    $lastCreatedAt = $transaction->created_at;
                    $lastId = $transaction->id;
                }
            } while ($batch->count() === 500);

            fclose($output);
        }, 'roi-report-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function levelIncomeReport(Request $request, ReportSummaryService $summaryService)
    {
        $query = $this->levelIncomeQuery($request);
        $level = $request->filled('level') ? (int) $request->query('level') : null;
        $totalAmount = $summaryService->levelCommissionReportTotal(
            $this->memberSearch($request),
            $level,
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) (clone $query)->sum('income_amount');
        $totalAmount = $this->formatDecimalAmount($totalAmount);
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $transactions = $query
            ->select(['id', 'reference', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
        $levels = LevelCommissionTransaction::query()
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level');

        return view('admin.reports.level-income', [
            'transactions' => $transactions,
            'totalAmount' => $totalAmount,
            'levels' => $levels,
        ]);
    }

    public function levelIncomeReportTotal(Request $request, ReportSummaryService $summaryService): JsonResponse
    {
        $request->validate([
            'member_id' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $totalAmount = $summaryService->levelCommissionReportTotal(
            $this->memberSearch($request),
            $request->filled('level') ? (int) $request->query('level') : null,
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) $this->levelIncomeQuery($request)->sum('income_amount');

        return response()->json(['total_amount' => $this->formatDecimalAmount($totalAmount)]);
    }

    public function levelIncomeReportEntries(Request $request, ReportSummaryService $summaryService): JsonResponse
    {
        $request->validate([
            'member_id' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'integer', 'min:1', 'max:255'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $totalEntries = $summaryService->levelCommissionReportTransactionCount(
            $this->memberSearch($request),
            $request->filled('level') ? (int) $request->query('level') : null,
            $this->dateKey($request, 'from_date'),
            $this->dateKey($request, 'to_date'),
        ) ?? (string) $this->levelIncomeQuery($request)->count();

        return response()->json(['total_entries' => $totalEntries]);
    }

    public function exportLevelIncomeReport(Request $request)
    {
        $transactions = $this->levelIncomeQuery($request)
            ->select(['id', 'reference', 'member_id', 'member_name', 'from_member_id', 'level', 'income_amount', 'on_amount', 'created_at'])
            ->latest('created_at')
            ->orderByDesc('id');

        return new ReadableStreamedResponse(function () use ($transactions) {
            $output = fopen('php://temp', 'r+');
            fputcsv($output, ['Serial No', 'Reference', 'Member ID', 'Name', 'Income Amount (USDT)', 'On Amount (USDT)', 'From Member ID', 'From Level', 'Date']);

            $serial = 0;
            $lastCreatedAt = null;
            $lastId = null;

            do {
                $batch = (clone $transactions)
                    ->when($lastCreatedAt !== null, function ($query) use ($lastCreatedAt, $lastId): void {
                        $query->where(function ($cursor) use ($lastCreatedAt, $lastId): void {
                            $cursor->where('created_at', '<', $lastCreatedAt)
                                ->orWhere(function ($tie) use ($lastCreatedAt, $lastId): void {
                                    $tie->where('created_at', $lastCreatedAt)
                                        ->where('id', '<', $lastId);
                                });
                        });
                    })
                    ->limit(1000)
                    ->get();

                foreach ($batch as $transaction) {
                    fputcsv($output, [
                        ++$serial,
                        $transaction->reference,
                        $transaction->member_id,
                        $transaction->member_name,
                        $this->formatUsdt($transaction->income_amount),
                        $this->formatUsdt($transaction->on_amount),
                        $transaction->from_member_id,
                        $transaction->level,
                        $transaction->created_at?->format('d-M-Y'),
                    ]);

                    $lastCreatedAt = $transaction->created_at;
                    $lastId = $transaction->id;
                }
            } while ($batch->count() === 1000);

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return $csv === false ? '' : $csv;
        }, 'level-commission-report-' . now()->format('Y-m-d') . '.csv');
    }

    public function salaryReport(Request $request)
    {
        return view('admin.reports.salary');
    }

    public function rankAchievementReport(Request $request, RankService $rankService)
    {
        $query = $this->rankAchievementQuery($request);
        $totalAmount = $this->rankAchievementTotal($request, $rankService);
        $achievements = $query->latest('achieved_at')->paginate(10)->withQueryString();
        $ranks = \App\Models\Rank::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.reports.rank-achievement', [
            'achievements' => $achievements,
            'totalAmount' => $totalAmount,
            'ranks' => $ranks,
        ]);
    }

    protected function rankAchievementTotal(Request $request, RankService $rankService): string
    {
        if (! $request->filled('member_id')) {
            return (string) RankAchievement::query()
                ->sum('qualifying_business_amount');
        }

        $memberIds = Member::query()
            ->where('member_id', 'like', '%' . trim((string) $request->query('member_id')) . '%')
            ->pluck('member_id')
            ->all();

        if ($memberIds === []) {
            return '0.0000';
        }

        $calculations = $rankService->calculateForMembers(
            $memberIds,
            $request->query('from_date'),
            $request->query('to_date')
        );

        $total = '0.0000';
        foreach ($calculations as $calculation) {
            $total = bcadd($total, (string) $calculation['full_team_business'], 4);
        }

        return $total;
    }

    protected function roiQuery(Request $request)
    {
        $query = RoiTransaction::query();

        $this->applyCommonFilters($query, $request);

        return $query;
    }

    protected function levelIncomeQuery(Request $request)
    {
        $query = LevelCommissionTransaction::query();

        $this->applyCommonFilters($query, $request);

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        return $query;
    }

    protected function rankAchievementQuery(Request $request)
    {
        $query = RankAchievement::query()
            ->select(['id', 'member_id', 'member_name', 'rank_id', 'achieved_at'])
            ->with('rank:id,name');

        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        if ($request->filled('rank_id')) {
            $rankId = trim((string) $request->query('rank_id'));
            if ($rankId !== '') {
                $query->where('rank_id', $rankId);
            }
        }

        if ($from = QueryDate::day($request->query('from_date'))) {
            $query->where('achieved_at', '>=', $from);
        }

        if ($to = QueryDate::day($request->query('to_date'))) {
            $query->where('achieved_at', '<', $to->addDay());
        }

        return $query;
    }

    protected function applyCommonFilters($query, Request $request): void
    {
        if ($request->filled('member_id')) {
            $memberId = trim((string) $request->query('member_id'));
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        if ($from = QueryDate::day($request->query('from_date'))) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = QueryDate::day($request->query('to_date'))) {
            $query->where('created_at', '<', $to->addDay());
        }
    }

    protected function memberSearch(Request $request): ?string
    {
        return $request->filled('member_id') ? trim((string) $request->query('member_id')) : null;
    }

    protected function dateKey(Request $request, string $field): ?int
    {
        return QueryDate::key($request->query($field));
    }

    protected function formatDecimalAmount($amount): string
    {
        $formatted = bcadd((string) $amount, '0', 4);

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    protected function formatUsdt($amount): string
    {
        return \App\Support\MoneyDisplay::usdt($amount);
    }
}
