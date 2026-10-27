<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Support\WithdrawalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WithdrawalReviewController extends Controller
{
    public function overview(): View
    {
        $counts = WithdrawalRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.withdrawals.overview', [
            'total' => (int) $counts->sum(),
            'counts' => [
                WithdrawalStatus::PENDING_REVIEW => (int) ($counts[WithdrawalStatus::PENDING_REVIEW] ?? 0),
                WithdrawalStatus::APPROVED => (int) ($counts[WithdrawalStatus::APPROVED] ?? 0),
                WithdrawalStatus::PROCESSING => (int) ($counts[WithdrawalStatus::PROCESSING] ?? 0),
                WithdrawalStatus::COMPLETED => (int) ($counts[WithdrawalStatus::COMPLETED] ?? 0),
                WithdrawalStatus::REJECTED => (int) ($counts[WithdrawalStatus::REJECTED] ?? 0),
                WithdrawalStatus::FAILED => (int) ($counts[WithdrawalStatus::FAILED] ?? 0),
                WithdrawalStatus::CANCELLED => (int) ($counts[WithdrawalStatus::CANCELLED] ?? 0),
            ],
        ]);
    }

    public function index(Request $request): View
    {
        return view('admin.withdrawals.index', [
            'withdrawals' => $this->filtered($request)->latest('id')->paginate(10)->withQueryString(),
            'statuses' => WithdrawalStatus::VALUES,
        ]);
    }

    public function show(WithdrawalRequest $withdrawalRequest): View
    {
        $withdrawalRequest->load(['statusEvents' => fn ($query) => $query->orderBy('id')]);

        return view('admin.withdrawals.show', [
            'withdrawal' => $withdrawalRequest,
        ]);
    }

    public function payouts(): View
    {
        return view('admin.withdrawals.payouts', [
            'payouts' => WithdrawalRequest::query()
                ->where('status', WithdrawalStatus::COMPLETED)
                ->whereNotNull('payout_txid')
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $status = (string) $request->query('status', '');
        $from = (string) $request->query('from_date', '');
        $to = (string) $request->query('to_date', '');

        return WithdrawalRequest::query()
            ->when($request->filled('member_id'), fn (Builder $query) => $query->where('member_id', trim((string) $request->query('member_id'))))
            ->when($request->filled('reference'), fn (Builder $query) => $query->where('reference', trim((string) $request->query('reference'))))
            ->when(in_array($status, WithdrawalStatus::VALUES, true), fn (Builder $query) => $query->where('status', $status))
            ->when($request->filled('network'), fn (Builder $query) => $query->where('network', trim((string) $request->query('network'))))
            ->when($this->isDate($from), fn (Builder $query) => $query->whereDate('created_at', '>=', $from))
            ->when($this->isDate($to), fn (Builder $query) => $query->whereDate('created_at', '<=', $to));
    }

    private function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
