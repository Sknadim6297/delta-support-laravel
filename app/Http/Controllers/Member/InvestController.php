<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvestController extends Controller
{
	public function entry(Request $request): View
	{
		return view('member.invesment.investment-entry', [
			'member' => $this->currentMember($request),
		]);
	}

	public function store(Request $request): RedirectResponse
	{
		$member = $this->currentMember($request);
		$investmentId = $this->generateInvestmentId();

		$validated = $request->validate([
			'amount' => ['required', 'numeric', 'min:100', 'decimal:0,4'],
		], [
			'amount.min' => 'Investment Amount must be at least 100 USDT.',
		]);

		DB::transaction(function () use ($member, $validated, $investmentId) {
			$member = Member::whereKey($member->id)->lockForUpdate()->firstOrFail();

			$investment = Investment::create([
				'investment_id' => $investmentId,
				'member_id' => $member->member_id,
				'member_name' => $member->member_name,
				'amount' => $validated['amount'],
				'status' => 'active',
			]);

			if ($member->status !== 'active') {
				$member->update(['status' => 'active']);
			}
		});

		return redirect()->route('member.investments.entry')
			->with('success', 'Investment entry created successfully.');
	}

	public function active(Request $request): View
	{
		$member = $this->currentMember($request);
		$query = Investment::query()
			->where('member_id', $member->member_id)
			->where('status', 'active')
			->where('amount', '>=', 100);

		$this->applyCreatedDateFilters($query, $request);

		return view('member.invesment.active-invesment-list', [
			'member' => $member,
			'investments' => $query->latest()->paginate(10)->withQueryString(),
			'totalAmount' => (clone $query)->sum('amount'),
		]);
	}

	public function closed(Request $request): View
	{
		$member = $this->currentMember($request);
		$query = Investment::query()
			->where('member_id', $member->member_id)
			->where('status', 'expired')
			->where('amount', '>=', 100);

		if ($request->filled('from_date')) {
			$query->whereDate('closed_at', '>=', $request->query('from_date'));
		}

		if ($request->filled('to_date')) {
			$query->whereDate('closed_at', '<=', $request->query('to_date'));
		}

		return view('member.invesment.closed-investment-list', [
			'member' => $member,
			'investments' => $query->latest('closed_at')->paginate(10)->withQueryString(),
			'totalAmount' => (clone $query)->sum('amount'),
		]);
	}

	private function currentMember(Request $request): Member
	{
		return Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
	}

	private function applyCreatedDateFilters($query, Request $request): void
	{
		if ($request->filled('from_date')) {
			$query->whereDate('created_at', '>=', $request->query('from_date'));
		}

		if ($request->filled('to_date')) {
			$query->whereDate('created_at', '<=', $request->query('to_date'));
		}
	}

	private function generateInvestmentId(): string
	{
		do {
			$investmentId = 'INV' . random_int(100000, 999999);
		} while (Investment::where('investment_id', $investmentId)->exists());

		return $investmentId;
	}
}
