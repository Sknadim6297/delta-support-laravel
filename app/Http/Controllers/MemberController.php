<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Investment;
use App\Models\InvestmentWithdrawal;
use App\Models\LevelCommissionTransaction;
use App\Models\PackageLevelAccrual;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use App\Services\MemberRegistrationService;
use App\Services\RankService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    protected function formatMembers($members): array
    {
        return $members
            ->map(function (Member $member, $index) {
                return [
                    'serial' => $index + 1,
                    'id' => $member->id,
                    'member_id' => $member->member_id,
                    'name' => $member->member_name,
                    'joining_date' => $member->created_at ? $member->created_at->format('d-M-Y') : 'N/A',
                    'sponsor_id' => $member->sponsor_id,
                    'sponsor_name' => $member->sponsor_name,
                    'email' => $member->email,
                    'mobile' => $member->mobile_no,
                    'pan_card_no' => $member->pan_card_no,
                    'investment_amount' => (float) ($member->investment_amount ?? 0),
                    'status' => $member->status,
                    'account_status' => $member->status === 'active' ? 'Active' : 'Inactive',
                    'package_status' => $member->has_approved_package
                        ? 'Approved package'
                        : ($member->has_pending_package ? 'Pending package' : 'Unpaid'),
                ];
            })
            ->values()
            ->all();
    }

    protected function generateMemberId(): string
    {
        for ($counter = 100000; $counter <= 999999; $counter++) {
            $candidate = 'ST' . $counter;
            if ($candidate === MemberRegistrationService::ADMIN_SPONSOR_ID) {
                continue;
            }

            if (! Member::where('member_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No available Member ID found.');
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone) ?? '';
    }

    protected function normalizeMemberName(string $memberName): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim((string) $memberName));

        return $normalized ?? '';
    }

    protected function resolveSponsorId(): string
    {
        $user = auth()->user();

        if (! $user) {
            return 'ST666666';
        }

        if (strtolower($user->email) === 'admin@gmail.com') {
            return 'ST666666';
        }

        $member = Member::where('email', $user->email)->first();

        return $member?->member_id ?? 'ST666666';
    }

    protected function resolveSponsorName(string $sponsorId): string
    {
        if ($sponsorId === 'ST666666') {
            return 'Admin';
        }

        $member = Member::where('member_id', $sponsorId)->first();

        return $member?->member_name ?? 'Admin';
    }

    protected function resolveSponsorDetails(Request $request): array
    {
        $requestedSponsorId = trim((string) $request->input('sponsor_id', ''));

        if ($requestedSponsorId === '') {
            $sponsorId = $this->resolveSponsorId();

            return [
                'sponsor_id' => $sponsorId,
                'sponsor_name' => $this->resolveSponsorName($sponsorId),
            ];
        }

        if (strtoupper($requestedSponsorId) === 'ST666666') {
            return [
                'sponsor_id' => 'ST666666',
                'sponsor_name' => 'Admin',
            ];
        }

        $member = Member::where('member_id', $requestedSponsorId)->first();

        if (! $member) {
            // Reject arbitrary/non-existing Sponsor IDs instead of silently defaulting to Admin.
            throw ValidationException::withMessages([
                'sponsor_id' => 'Invalid Sponsor ID. Enter an existing Member ID or leave it blank to use the default sponsor.',
            ]);
        }

        return [
            'sponsor_id' => $member->member_id,
            'sponsor_name' => $member->member_name,
        ];
    }

    public function active()
    {
        return $this->memberList(request(), 'active', 'admin.members.active-member');
    }

    public function inactive()
    {
        return $this->memberList(request(), 'inactive', 'admin.members.inactive');
    }

    public function searchMembers(Request $request)
    {
        $member = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
        $term = trim((string) $request->query('member_id', ''));

        if ($term === '') {
            return response()->json([]);
        }

        $authorizedIds = Member::authorizedMemberIds($member->member_id);

        $results = Member::query()
            ->whereIn('member_id', $authorizedIds)
            ->where(function ($query) use ($term) {
                $query->where('member_id', 'like', '%' . $term . '%')
                    ->orWhere('member_name', 'like', '%' . $term . '%');
            })
            ->select(['member_id', 'member_name', 'status'])
            ->orderBy('member_id')
            ->limit(10)
            ->get()
            ->map(fn (Member $item) => [
                'member_id' => $item->member_id,
                'member_name' => $item->member_name,
                'status' => $item->status,
            ])
            ->values()
            ->all();

        return response()->json($results);
    }

    public function lookupMember(Request $request)
    {
        $member = Member::where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
        $requestedMemberId = trim((string) $request->query('member_id', ''));

        if ($requestedMemberId === '') {
            return response()->json(['message' => 'Member ID is required.'], 422);
        }

        $authorizedIds = Member::authorizedMemberIds($member->member_id);
        $target = Member::query()
            ->whereIn('member_id', $authorizedIds)
            ->where('member_id', $requestedMemberId)
            ->first();

        if (! $target) {
            return response()->json(['message' => 'The selected member id is invalid.'], 404);
        }

        return response()->json([
            'member_id' => $target->member_id,
            'member_name' => $target->member_name,
            'status' => $target->status,
        ]);
    }

    public function memberPanel(string $memberId, Request $request)
    {
        $member = Member::where('member_id', $memberId)->firstOrFail();

        $totalInvestment = (float) Investment::where('member_id', $member->member_id)->sum('amount');
        $activeInvestment = (float) Investment::where('member_id', $member->member_id)
            ->where('status', 'active')
            ->sum('amount');
        $withdrawnAmount = (float) InvestmentWithdrawal::where('member_id', $member->member_id)
            ->sum('withdrawal_amount');
        $teamMemberIds = $this->descendantMemberIds($member->member_id);
        $teamActiveInvestment = (float) Investment::whereIn('member_id', $teamMemberIds)
            ->where('status', 'active')
            ->sum('amount');
        $teamInactiveInvestment = (float) Investment::whereIn('member_id', $teamMemberIds)
            ->where('status', 'active')
            ->whereHas('member', fn ($query) => $query->where('status', 'inactive'))
            ->sum('amount');
        $rankService = app(RankService::class);
        if (! $request->routeIs('admin.member-panel')) {
            $rankService->syncMemberRankAchievement($member);
            $member->refresh();
        }
        $rankData = $rankService->calculateForMember($member);

        $request->session()->put('member_context_id', $member->member_id);

        $approvedPackage = \App\Models\PackagePurchase::query()
            ->where('member_id', $member->member_id)
            ->where('status', \App\Models\PackagePurchase::APPROVED)
            ->latest('reviewed_at')
            ->first();
        $recentPurchases = \App\Models\PackagePurchase::query()
            ->where('member_id', $member->member_id)
            ->latest('submitted_at')
            ->limit(5)
            ->get();

        return view('member.dashboard', [
            'member' => $member,
            'approvedPackage' => $approvedPackage,
            'recentPurchases' => $recentPurchases,
            'packageOptions' => \App\Models\Package::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'directReferrals' => Member::where('sponsor_id', $member->member_id)->count(),
            'metrics' => [
                'totalInvestment' => $totalInvestment,
                'activeInvestment' => $activeInvestment,
                'remainingBalance' => max(0, $totalInvestment - $withdrawnAmount),
                'roiWallet' => (float) $member->roi_wallet_amount,
                'workingWallet' => (float) $member->working_wallet_amount,
                'salaryWallet' => (float) ($member->salary_wallet_amount ?? 0),
                'activationWallet' => (float) $member->activation_wallet_amount,
                'roiIncome' => (float) bcadd(
                    (string) RoiTransaction::where('member_id', $member->member_id)->sum('income_amount'),
                    (string) PackageRoiAccrual::where('member_id', $member->member_id)->sum('amount'),
                    4
                ),
                'levelIncome' => (float) bcadd(
                    (string) LevelCommissionTransaction::where('member_id', $member->member_id)->sum('income_amount'),
                    (string) PackageLevelAccrual::where('member_id', $member->member_id)->sum('amount'),
                    4
                ),
                'salaryIncome' => 0,
                'activeMembers' => Member::where('sponsor_id', $member->member_id)->where('status', 'active')->count(),
                'inactiveMembers' => Member::where('sponsor_id', $member->member_id)->where('status', 'inactive')->count(),
                'rank' => $rankData['current_rank']?->name ?? 'Unranked',
                'rankSummary' => $rankData,
                'teamActiveInvestment' => $teamActiveInvestment,
                'teamInactiveInvestment' => $teamInactiveInvestment,
                'teamActiveInvestmentRatio' => $teamActiveInvestment . ':'. $teamInactiveInvestment,
                'totalWithdrawal' => $withdrawnAmount,
                'packagePurchases' => \App\Support\CommerceTotals::sums(\App\Models\PackagePurchase::class, null, $member->member_id),
                'referralUrl' => route('member.register', ['sponsor_id' => $member->member_id]),
            ],
        ]);
    }

    protected function descendantMemberIds(string $memberId): array
    {
        $ids = [];
        $pending = [$memberId];

        while ($pending !== []) {
            $children = Member::whereIn('sponsor_id', $pending)->pluck('member_id')->all();
            $children = array_values(array_diff($children, $ids, [$memberId]));

            if ($children === []) {
                break;
            }

            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return $ids === [] ? [$memberId] : array_merge([$memberId], $ids);
    }

    protected function memberList(Request $request, string $status, string $view)
    {
        $query = Member::where('status', $status)
            ->withSum(['investments as investment_amount' => function ($investmentQuery) {
                $investmentQuery->where('status', 'active')->where('amount', '>=', 100);
            }], 'amount')
            ->withExists([
                'packagePurchases as has_approved_package' => fn ($purchaseQuery) => $purchaseQuery->where('status', \App\Models\PackagePurchase::APPROVED),
                'packagePurchases as has_pending_package' => fn ($purchaseQuery) => $purchaseQuery->where('status', \App\Models\PackagePurchase::PENDING),
            ]);

        $memberName = trim((string) $request->query('member_name', ''));
        $memberId = trim((string) $request->query('member_id', ''));

        if ($memberName !== '') {
            $query->where(function ($memberQuery) use ($memberName) {
                $memberQuery->where('member_name', 'like', '%' . $memberName . '%')
                    ->orWhere('email', 'like', '%' . $memberName . '%')
                    ->orWhere('mobile_no', 'like', '%' . $memberName . '%')
                    ->orWhere('sponsor_id', 'like', '%' . $memberName . '%')
                    ->orWhere('sponsor_name', 'like', '%' . $memberName . '%');
            });
        }

        if ($memberId !== '') {
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        $members = $query->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();
        $members->setCollection(collect($this->formatMembers($members->getCollection())));
        $members->getCollection()->transform(function (array $member, int $index) use ($members) {
            $member['serial'] = ($members->currentPage() - 1) * $members->perPage() + $index + 1;

            return $member;
        });

        $statusCounts = Member::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $activeMemberCount = (int) $statusCounts->get('active', 0);
        $inactiveMemberCount = (int) $statusCounts->get('inactive', 0);
        $totalMembers = (int) Member::query()->count();
        $approvedPackageCount = \App\Models\PackagePurchase::query()
            ->where('status', \App\Models\PackagePurchase::APPROVED)
            ->distinct()
            ->count('member_id');

        return view($view, compact(
            'members',
            'memberName',
            'memberId',
            'activeMemberCount',
            'inactiveMemberCount',
            'totalMembers',
            'approvedPackageCount',
        ));
    }

    public function export(Request $request, string $status)
    {
        abort_unless(in_array($status, ['active', 'inactive'], true), 404);

        $query = Member::where('status', $status)
            ->withSum(['investments as investment_amount' => function ($investmentQuery) {
                $investmentQuery->where('status', 'active')->where('amount', '>=', 100);
            }], 'amount');

        $memberName = trim((string) $request->query('member_name', ''));
        $memberId = trim((string) $request->query('member_id', ''));

        if ($memberName !== '') {
            $query->where(function ($memberQuery) use ($memberName) {
                $memberQuery->where('member_name', 'like', '%' . $memberName . '%')
                    ->orWhere('email', 'like', '%' . $memberName . '%')
                    ->orWhere('mobile_no', 'like', '%' . $memberName . '%')
                    ->orWhere('sponsor_id', 'like', '%' . $memberName . '%')
                    ->orWhere('sponsor_name', 'like', '%' . $memberName . '%');
            });
        }

        if ($memberId !== '') {
            $query->where('member_id', 'like', '%' . $memberId . '%');
        }

        $members = $query->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($members) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Serial No', 'Member ID', 'Member Name', 'Joining Date', 'Sponsor ID', 'Sponsor Name', 'Email', 'Mobile No', 'PAN Card Number', 'Account Status', 'Investment Amount (USDT)']);

            foreach ($members as $index => $member) {
                fputcsv($output, [
                    $index + 1,
                    $member->member_id,
                    $member->member_name,
                    $member->created_at?->format('d-M-Y') ?? 'N/A',
                    $member->sponsor_id,
                    $member->sponsor_name,
                    $member->email,
                    $member->mobile_no,
                    $member->pan_card_no,
                    $member->status === 'active' ? 'Active' : 'Inactive',
                    $this->formatUsdt($member->investment_amount ?? 0),
                ]);
            }

            fclose($output);
        }, $status . '-members-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function registration()
    {
        $generatedMemberId = session('generated_member_id');

        if (empty($generatedMemberId)) {
            $generatedMemberId = $this->generateMemberId();
            session()->put('generated_member_id', $generatedMemberId);
        }

        $members = Member::select('member_id', 'member_name')
            ->orderBy('member_id')
            ->get()
            ->map(function ($member) {
                return [
                    'member_id' => $member->member_id,
                    'member_name' => $member->member_name,
                ];
            })
            ->all();

        $defaultSponsorId = $this->resolveSponsorId();

        return view('admin.members.member_registration', [
            'members' => $members,
            'generated_member_id' => $generatedMemberId,
            'default_sponsor_id' => $defaultSponsorId,
            'default_sponsor_name' => $this->resolveSponsorName($defaultSponsorId),
        ]);
    }

    protected function formatUsdt($amount): string
    {
        return \App\Support\MoneyDisplay::usdt($amount);
    }

    protected function memberValidationRules(?Member $member = null): array
    {
        return [
            'member_name' => Member::nameRules(),
            'sponsor_id' => ['nullable', 'string'],
            'wallet_address' => ['nullable', 'string', 'max:255'],
            'mobile_no' => ['required', 'string', 'regex:/^[0-9+\-\s]+$/', 'min:10', 'max:15'],
            'pan_card_no' => ['required', 'string', 'max:10'],
            'email' => $member
                ? ['required', 'email', Rule::unique('members', 'email')->ignore($member->id)]
                : ['required', 'email', 'unique:members,email'],
            'password' => $member
                ? ['nullable', 'string', 'min:6']
                : ['nullable', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function update()
    {
        return view('admin.members.member_update', ['member' => []]);
    }

    public function fetchMemberDetails(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'message' => 'Member not found.',
            ], 404);
        }

        $approvedPackage = $member->packagePurchases()
            ->where('status', \App\Models\PackagePurchase::APPROVED)
            ->exists();

        return response()->json([
            'member_id' => $member->member_id,
            'sponsor_id' => $member->sponsor_id,
            'sponsor_name' => $member->sponsor_name,
            'name' => $member->member_name,
            'wallet' => $member->wallet_address,
            'mobile' => $member->mobile_no,
            'pan_card_no' => $member->pan_card_no,
            'email' => $member->email,
            'account_status' => $member->status,
            'registered_at' => $member->created_at?->format('d M Y'),
            'direct_referrals' => $member->sponsoredMembers()->count(),
            'package_status' => $approvedPackage ? 'Approved package' : 'Unpaid',
        ]);
    }

    public function checkMemberIdAvailability(Request $request)
    {
        $memberId = trim((string) $request->query('member_id', ''));
        $currentMemberId = trim((string) $request->query('current_member_id', ''));

        if ($memberId === '') {
            return response()->json([
                'available' => false,
                'message' => 'Member ID is required.',
            ]);
        }

        if (! preg_match('/^ST\d{6}$/', $memberId)) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid Member ID format.',
            ]);
        }

        if ($currentMemberId !== '' && strtoupper($currentMemberId) === strtoupper($memberId)) {
            return response()->json([
                'available' => true,
                'message' => 'Member ID is available.',
            ]);
        }

        $exists = Member::where('member_id', $memberId)->exists();

        return response()->json([
            'available' => ! $exists,
            'message' => $exists ? 'This Member ID already exists.' : 'Member ID is available.',
        ]);
    }

    public function checkSponsorIdAvailability(Request $request)
    {
        $sponsorId = trim((string) $request->query('sponsor_id', ''));

        if ($sponsorId === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Sponsor ID is required.',
            ]);
        }

        if ($sponsorId === 'ST666666') {
            return response()->json([
                'exists' => true,
                'sponsor_name' => 'Admin',
                'message' => 'Sponsor ID is valid.',
            ]);
        }

        $member = Member::where('member_id', $sponsorId)->first();

        if (! $member) {
            return response()->json([
                'exists' => false,
                'message' => 'Invalid Sponsor ID.',
            ]);
        }

        return response()->json([
            'exists' => true,
            'sponsor_name' => $member->member_name,
            'message' => 'Sponsor ID is valid.',
        ]);
    }

    public function store(Request $request)
    {
        $wallet = trim((string) $request->input('wallet_address'));
        $request->merge([
            'member_name' => trim((string) $request->input('member_name')),
            'sponsor_id' => strtoupper(trim((string) $request->input('sponsor_id'))),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
            'pan_card_no' => strtoupper(trim((string) $request->input('pan_card_no'))),
            'wallet_address' => $wallet === '' ? null : $wallet,
        ]);

        $validated = $request->validate($this->memberValidationRules());

        $result = app(MemberRegistrationService::class)->register($validated, [
            'require_pan' => true,
            'allow_admin_sponsor' => true,
            'blank_sponsor_uses_admin' => true,
        ]);

        $request->session()->forget('generated_member_id');

        $message = 'Member registration submitted successfully. Member ID: ' . $result['member']->member_id;
        if (! filled($validated['password'] ?? null)) {
            $message .= ' No password was set. The member can create one from Forgot Password.';
        }

        return redirect()->route('admin.members.registration')
            ->with('success', $message);
    }

    public function updateMember(Request $request)
    {
        $memberId = trim((string) $request->input('member_id'));
        $wallet = trim((string) $request->input('wallet_address'));
        $request->merge([
            'member_id' => $memberId,
            'member_name' => trim((string) $request->input('member_name')),
            'email' => trim((string) $request->input('email')),
            'mobile_no' => trim((string) $request->input('mobile_no')),
            'pan_card_no' => strtoupper(trim((string) $request->input('pan_card_no'))),
            'wallet_address' => $wallet === '' ? null : $wallet,
        ]);

        $request->validate([
            'member_id' => ['required', 'string', 'min:6', 'exists:members,member_id'],
        ]);

        $member = Member::where('member_id', $memberId)->first();

        if (! $member) {
            return back()->withErrors(['member_id' => 'Member not found.'])->withInput();
        }

        $validated = $request->validate($this->memberValidationRules($member));

        if (mb_strlen(trim((string) $validated['pan_card_no'])) !== 10) {
            return back()->withErrors(['pan_card_no' => 'PAN Card Number must be exactly 10 characters.'])->withInput();
        }

        $validated['pan_card_no'] = strtoupper(trim((string) $validated['pan_card_no']));

        $panCount = Member::whereRaw('UPPER(pan_card_no) = ?', [$validated['pan_card_no']])
            ->whereKeyNot($member->id)
            ->count();

        if ($panCount >= 3) {
            return back()->withErrors(['pan_card_no' => 'This PAN card number has already been used 3 times.'])->withInput();
        }

        $mobileNoValue = trim((string) $validated['mobile_no']);
        $mobileCount = Member::where('mobile_no', $mobileNoValue)
            ->whereKeyNot($member->id)
            ->count();

        if ($mobileCount >= 3) {
            return back()->withErrors(['mobile_no' => 'This mobile number has already been used 3 times.'])->withInput();
        }

        $memberData = [
            'member_name' => $validated['member_name'],
            'wallet_address' => $validated['wallet_address'] ?? null,
            'mobile_no' => $validated['mobile_no'],
            'pan_card_no' => $validated['pan_card_no'],
            'email' => $validated['email'],
        ];

        if ($request->filled('password')) {
            $memberData['password'] = bcrypt($validated['password']);
        }

        $member->update($memberData);

        return redirect()->route('admin.members.update', ['member_id' => $member->member_id])
            ->with('success', 'Member profile updated successfully.');
    }
}
