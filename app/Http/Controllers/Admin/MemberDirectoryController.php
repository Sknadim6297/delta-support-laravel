<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PackagePurchase;
use App\Services\MemberEmailAccountPolicy;
use App\Services\Rewards\TeamBusinessCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MemberDirectoryController extends Controller
{
    public function index(Request $request, TeamBusinessCalculationService $business): View
    {
        $packageFilter = trim((string) $request->query('package', ''));

        $members = Member::query()
            ->with('directoryPackagePurchases')
            ->withExists([
                'packagePurchases as has_approved_package' => fn ($query) => $query->where('status', PackagePurchase::APPROVED),
                'packagePurchases as has_pending_package' => fn ($query) => $query->where('status', PackagePurchase::PENDING),
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('member_id', 'like', $term)
                        ->orWhere('member_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('mobile_no', 'like', $term)
                        ->orWhere('sponsor_id', 'like', $term)
                        ->orWhere('sponsor_name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($packageFilter !== '', function ($query) use ($packageFilter): void {
                $query->whereHas('packagePurchases', function ($purchaseQuery) use ($packageFilter): void {
                    $purchaseQuery->where('status', PackagePurchase::APPROVED)
                        ->where('package_code', $packageFilter);
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        $businessSummaries = $business->businessForMembers($members->getCollection()->pluck('member_id')->all());

        $statusCounts = Member::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $activeMemberCount = (int) $statusCounts->get('active', 0);
        $inactiveMemberCount = (int) $statusCounts->get('inactive', 0);
        $totalMembers = (int) Member::query()->count();
        $approvedPackageCount = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->distinct()
            ->count('member_id');
        $packageOptions = PackagePurchase::query()
            ->where('status', PackagePurchase::APPROVED)
            ->select('package_code', 'package_name')
            ->distinct()
            ->orderBy('package_code')
            ->get();
        $policy = app(MemberEmailAccountPolicy::class);
        $emailAccountCounts = [];
        foreach ($members->getCollection() as $member) {
            $normalized = $policy->normalize((string) $member->email);
            $emailAccountCounts[$normalized] = $policy->count($normalized);
        }

        return view('admin.members.directory', compact(
            'members',
            'totalMembers',
            'activeMemberCount',
            'inactiveMemberCount',
            'approvedPackageCount',
            'packageOptions',
            'packageFilter',
            'businessSummaries',
            'emailAccountCounts',
        ));
    }

    public function updateStatus(Request $request, string $memberId): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
        ]);

        $member = Member::query()->where('member_id', $memberId)->firstOrFail();
        $member->update(['status' => $data['status']]);

        return back()->with('success', $member->member_id . ' is now ' . $data['status'] . '. Wallet balances were not changed.');
    }
}
