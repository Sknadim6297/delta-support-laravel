<?php

namespace App\Http\Controllers;

use App\Models\ActivationWalletTransaction;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivationWalletController extends Controller
{
    public function creditEntry()
    {
        return view('admin.activation-wallet.credit-entry');
    }

    public function debitEntry()
    {
        return view('admin.activation-wallet.debit-entry');
    }

    public function memberLookup(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json(['message' => 'The selected member id is invalid.'], 404);
        }

        return response()->json([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'activation_wallet_amount' => $member->activation_wallet_amount ?? 0,
            'working_wallet_amount' => $member->working_wallet_amount ?? 0,
        ]);
    }

    public function storeCreditEntry(Request $request)
    {
        $validated = $this->validateWalletAmount($request, 'Transfer amount must be greater than 0.');

        $transaction = DB::transaction(function () use ($validated) {
            $member = Member::where('member_id', $validated['member_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $amount = $this->normalizeAmount($validated['amount']);
            $workingBalance = $this->normalizeAmount($member->working_wallet_amount ?? 0);
            $activationBalance = $this->normalizeAmount($member->activation_wallet_amount ?? 0);

            if ($amount > $workingBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient Working Wallet balance.',
                ]);
            }

            $member->working_wallet_amount = $workingBalance - $amount;
            $member->activation_wallet_amount = $activationBalance + $amount;
            $member->save();

            return ActivationWalletTransaction::create([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => $amount,
                'type' => 'credit',
                'remarks' => 'Transfer from Working Wallet',
                'reference' => $this->generateReference('AW'),
            ]);
        });

        return redirect()->route('admin.activation-wallet.credit-entry')
            ->with('success', 'Activation Wallet transfer completed successfully.')
            ->with('transaction_id', $transaction->id);
    }

    public function storeDebitEntry(Request $request)
    {
        $validated = $this->validateWalletAmount($request, 'Debit amount must be greater than 0.', [
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = DB::transaction(function () use ($validated) {
            $member = Member::where('member_id', $validated['member_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $amount = $this->normalizeAmount($validated['amount']);
            $activationBalance = $this->normalizeAmount($member->activation_wallet_amount ?? 0);

            if ($amount > $activationBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient Activation Wallet balance.',
                ]);
            }

            $member->activation_wallet_amount = $activationBalance - $amount;
            $member->save();

            return ActivationWalletTransaction::create([
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => $amount,
                'type' => 'debit',
                'remarks' => trim((string) ($validated['remarks'] ?? '')) ?: 'Force debit',
                'reference' => $this->generateReference('AD'),
            ]);
        });

        return redirect()->route('admin.activation-wallet.debit-entry')
            ->with('success', 'Activation Wallet force debit completed successfully.')
            ->with('transaction_id', $transaction->id);
    }

    protected function validateWalletAmount(Request $request, string $gtMessage, array $extraRules = []): array
    {
        return $request->validate(array_merge([
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ], $extraRules), [
            'member_id.exists' => 'The selected member id is invalid.',
            'amount.gt' => $gtMessage,
        ]);
    }

    protected function transactionQuery(Request $request, ?string $type = null)
    {
        $query = ActivationWalletTransaction::query();

        if ($type) {
            $query->where('type', $type);
        }

        if ($request->filled('member_id')) {
            $query->where('member_id', 'like', '%' . trim((string) $request->query('member_id')) . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        return $query;
    }

    protected function validateDateFilters(Request $request): void
    {
        $toDateRules = ['nullable', 'date'];

        if ($request->filled('from_date')) {
            $toDateRules[] = 'after_or_equal:from_date';
        }

        $request->validate([
            'member_id' => ['nullable', 'string'],
            'from_date' => ['nullable', 'date'],
            'to_date' => $toDateRules,
        ]);
    }

    public function listCreditEntries(Request $request)
    {
        $this->validateDateFilters($request);
        $query = $this->transactionQuery($request, 'credit');

        return view('admin.activation-wallet.credit-entry-list', [
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => (clone $query)->sum('amount'),
        ]);
    }

    public function exportCreditEntries(Request $request)
    {
        $this->validateDateFilters($request);
        $transactions = $this->transactionQuery($request, 'credit')->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'To Member ID', 'To Member Name', 'Amount (USDT)', 'Transfer Date']);

            foreach ($transactions as $index => $transaction) {
                fputcsv($output, [
                    $index + 1,
                    $transaction->member_id,
                    $transaction->member_name,
                    $this->formatUsdt($transaction->amount),
                    $transaction->created_at?->format('d-M-Y H:i:s'),
                ]);
            }

            fclose($output);
        }, 'activation-wallet-transfers-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function listDebitEntries(Request $request)
    {
        $this->validateDateFilters($request);
        $query = $this->transactionQuery($request, 'debit');

        return view('admin.activation-wallet.debit-entry-list', [
            'transactions' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => (clone $query)->sum('amount'),
        ]);
    }

    public function summary(Request $request)
    {
        $request->validate([
            'member_id' => ['nullable', 'string'],
        ]);

        $query = $this->summaryQuery($request);

        return view('admin.activation-wallet.summary', [
            'members' => $query->orderBy('member_id')->paginate(10)->withQueryString(),
            'totalAmount' => (clone $query)->sum('activation_wallet_amount'),
        ]);
    }

    public function exportSummary(Request $request)
    {
        $request->validate([
            'member_id' => ['nullable', 'string'],
        ]);

        $members = $this->summaryQuery($request)->orderBy('member_id')->get();

        return response()->streamDownload(function () use ($members) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Member Name', 'Act. Wallet Balance (USDT)']);

            foreach ($members as $index => $member) {
                fputcsv($output, [
                    $index + 1,
                    $member->member_id,
                    $member->member_name,
                    $this->formatUsdt($member->activation_wallet_amount),
                ]);
            }

            fclose($output);
        }, 'activation-wallet-summary-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function summaryQuery(Request $request)
    {
        $query = Member::query()->select('member_id', 'member_name', 'activation_wallet_amount');

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($memberQuery) use ($memberSearch) {
                $memberQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        return $query;
    }

    protected function generateReference(string $prefix): string
    {
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(6)));
    }

    protected function normalizeAmount($amount): float
    {
        return round((float) $amount, 4);
    }

    protected function formatUsdt($amount): string
    {
        return \App\Support\MoneyDisplay::usdt($amount);
    }
}
