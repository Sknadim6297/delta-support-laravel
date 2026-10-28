<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use App\Models\InvestmentWithdrawal;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    public function investmentEntry(Request $request)
    {
        $investmentId = trim((string) $request->old('investment_id', ''));

        if ($investmentId === '') {
            $investmentId = trim((string) $request->session()->get('generated_investment_id', ''));
        }

        if ($investmentId === '') {
            $investmentId = $this->generateInvestmentId();
            $request->session()->put('generated_investment_id', $investmentId);
        }

        return view('admin.invesment.investment-entry', [
            'investmentId' => $investmentId,
        ]);
    }

    public function storeInvestment(Request $request)
    {
        $investmentId = trim((string) $request->input('investment_id', ''));

        if ($investmentId === '') {
            $investmentId = trim((string) $request->session()->get('generated_investment_id', ''));
        }

        if ($investmentId === '') {
            $investmentId = $this->generateInvestmentId();
        }

        $validatedData = $request->validate([
            'investment_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('investments', 'investment_id'),
            ],
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'amount' => ['required', 'numeric', 'min:100', 'decimal:0,4'],
        ], [
            'member_id.exists' => 'The selected member id is invalid.',
            'amount.min' => 'Investment Amount must be at least 100 USDT.',
        ]);

        $validatedData['investment_id'] = $investmentId;

        DB::transaction(function () use ($validatedData) {
            $member = Member::where('member_id', $validatedData['member_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $investment = Investment::create([
                'investment_id' => $validatedData['investment_id'],
                'member_id' => $member->member_id,
                'member_name' => $member->member_name,
                'amount' => $validatedData['amount'],
                'status' => 'active',
            ]);

            if ($member->status !== 'active') {
                $member->update(['status' => 'active']);
            }
        });

        $request->session()->forget('generated_investment_id');

        return redirect()->route('admin.investments.entry')->with('success', 'Investment entry created successfully.');
    }

    public function memberLookup(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'message' => 'The selected member id is invalid.',
            ], 404);
        }

        return response()->json([
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
        ]);
    }

    public function activeInvestments(Request $request)
    {
        $query = Investment::with('member')
            ->where('status', 'active')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($memberSearch) {
                $investmentQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $totalAmount = (clone $query)->sum('amount');

        return view('admin.invesment.active-invesment-list', [
            'investments' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportActiveInvestments(Request $request)
    {
        $query = Investment::where('status', 'active')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $memberSearch = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($memberSearch) {
                $investmentQuery->where('member_id', 'like', '%' . $memberSearch . '%')
                    ->orWhere('member_name', 'like', '%' . $memberSearch . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $investments = $query->latest()->get();

        return response()->streamDownload(function () use ($investments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Investment ID', 'Investment Amount (USDT)', 'Investment Date']);

            foreach ($investments as $index => $investment) {
                fputcsv($output, [
                    $index + 1,
                    $investment->member_id,
                    $investment->member_name,
                    $investment->investment_id,
                    $this->formatUsdt($investment->amount),
                    $investment->created_at?->format('d-m-Y'),
                ]);
            }

            fclose($output);
        }, 'active-investments-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function generateInvestmentId(): string
    {
        do {
            $investmentId = 'INV' . random_int(100000, 999999);
        } while (Investment::where('investment_id', $investmentId)->exists());

        return $investmentId;
    }

    protected function formatUsdt($amount): string
    {
        return \App\Support\MoneyDisplay::usdt($amount);
    }
    public function closedInvestments(Request $request)
    {
        $query = $this->closedInvestmentQuery($request);

        $totalAmount = (clone $query)->sum('amount');

        return view('admin.invesment.closed-investment-list', [
            'investments' => $query->latest()->paginate(10)->withQueryString(),
            'totalAmount' => $totalAmount,
        ]);
    }

    public function exportClosedInvestments(Request $request)
    {
        $investments = $this->closedInvestmentQuery($request)->latest('closed_at')->get();

        return response()->streamDownload(function () use ($investments) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Name', 'Investment ID', 'Investment Amount (USDT)', 'Investment Date', 'Close Date']);

            foreach ($investments as $index => $investment) {
                fputcsv($output, [
                    $index + 1,
                    $investment->member_id,
                    $investment->member_name,
                    $investment->investment_id,
                    $this->formatUsdt($investment->amount),
                    $investment->created_at?->format('d-m-Y'),
                    $investment->closed_at?->format('d-m-Y'),
                ]);
            }

            fclose($output);
        }, 'closed-investments-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function closedInvestmentQuery(Request $request)
    {
        $query = Investment::query()
            ->where('status', 'expired')
            ->where('amount', '>=', 100);

        if ($request->filled('member_id')) {
            $search = trim((string) $request->query('member_id'));
            $query->where(function ($investmentQuery) use ($search) {
                $investmentQuery->where('member_id', 'like', '%' . $search . '%')
                    ->orWhere('member_name', 'like', '%' . $search . '%')
                    ->orWhere('investment_id', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('closed_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('closed_at', '<=', $request->query('to_date'));
        }

        return $query;
    }
    public function investmentWithdrawalEntry()
    {
        return view('admin.invesment.investment-withdrawal-entry');
    }

    public function investmentWithdrawalLookup(Request $request)
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'investment_id' => ['required', 'string'],
        ]);

        $investment = Investment::where('investment_id', $validated['investment_id'])
            ->where('member_id', $validated['member_id'])
            ->first();

        if (! $investment) {
            return response()->json(['message' => 'The investment does not belong to the selected member.'], 422);
        }

        $available = $this->availableWithdrawalAmount($investment);

        return response()->json([
            'member_id' => $investment->member_id,
            'member_name' => $investment->member_name,
            'investment_id' => $investment->investment_id,
            'investment_amount' => $investment->amount,
            'available_amount' => $available,
            'eligible' => $investment->status === 'expired' && bccomp($available, '0', 4) > 0,
            'message' => $investment->status === 'expired' ? null : 'Only expired investments are eligible for withdrawal.',
        ]);
    }

    public function investmentWithdrawalInvestmentLookup(Request $request)
    {
        $validated = $request->validate([
            'investment_id' => ['required', 'string', 'exists:investments,investment_id'],
        ]);

        $investment = Investment::where('investment_id', $validated['investment_id'])->firstOrFail();

        return response()->json([
            'investment_id' => $investment->investment_id,
            'investment_amount' => $investment->amount,
        ]);
    }

    public function storeInvestmentWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string', 'exists:members,member_id'],
            'investment_id' => ['required', 'string', 'exists:investments,investment_id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ], [
            'amount.gt' => 'Withdrawal amount must be greater than 0.',
        ]);

        DB::transaction(function () use ($validated) {
            $investment = Investment::query()
                ->where('investment_id', $validated['investment_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($investment->member_id !== $validated['member_id']) {
                throw ValidationException::withMessages(['investment_id' => 'The selected investment does not belong to this member.']);
            }

            if ($investment->status !== 'expired') {
                throw ValidationException::withMessages(['investment_id' => 'Only expired investments are eligible for withdrawal.']);
            }

            if (InvestmentWithdrawal::where('investment_id', $investment->investment_id)->exists()) {
                throw ValidationException::withMessages(['investment_id' => 'A withdrawal request already exists for this investment.']);
            }

            $available = $this->availableWithdrawalAmount($investment);

            if (bccomp((string) $validated['amount'], $available, 4) > 0) {
                throw ValidationException::withMessages(['amount' => 'Withdrawal amount exceeds the available amount.']);
            }

            InvestmentWithdrawal::create([
                'withdrawal_id' => $this->generateWithdrawalId(),
                'member_id' => $investment->member_id,
                'member_name' => $investment->member_name,
                'investment_id' => $investment->investment_id,
                'investment_amount' => $investment->amount,
                'withdrawal_amount' => $validated['amount'],
                'status' => 'pending',
                'withdrawn_at' => now(),
            ]);
        }, 3);

        return redirect()->route('admin.investments.investment-withdrawal-entry')
            ->with('success', 'Investment withdrawal request created successfully.');
    }

    public function investmentWithdrawalList(Request $request)
    {
        $query = InvestmentWithdrawal::query();

        if ($request->filled('member_id')) {
            $search = trim((string) $request->query('member_id'));
            $query->where(function ($withdrawalQuery) use ($search) {
                $withdrawalQuery->where('member_id', 'like', '%' . $search . '%')
                    ->orWhere('member_name', 'like', '%' . $search . '%')
                    ->orWhere('investment_id', 'like', '%' . $search . '%')
                    ->orWhere('withdrawal_id', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('withdrawn_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('withdrawn_at', '<=', $request->query('to_date'));
        }

        return view('admin.invesment.investment-withdrawal-list', [
            'withdrawals' => $query->latest('withdrawn_at')->paginate(10)->withQueryString(),
            'totalAmount' => (clone $query)->sum('withdrawal_amount'),
        ]);
    }

    protected function availableWithdrawalAmount(Investment $investment): string
    {
        $withdrawn = (string) InvestmentWithdrawal::query()
            ->where('investment_id', $investment->investment_id)
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('withdrawal_amount');

        return bcsub((string) $investment->amount, $withdrawn, 4);
    }

    protected function generateWithdrawalId(): string
    {
        do {
            $withdrawalId = 'IWD' . random_int(100000, 999999);
        } while (InvestmentWithdrawal::where('withdrawal_id', $withdrawalId)->exists());

        return $withdrawalId;
    }

}