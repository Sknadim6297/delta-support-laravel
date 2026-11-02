<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Services\PackagePurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class CustomerPurchaseController extends Controller
{
    public function create(): View
    {
        return view('admin.purchases.create', [
            'packages' => Package::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function member(Request $request): JsonResponse
    {
        $memberId = strtoupper(trim((string) $request->query('member_id', '')));
        $member = $memberId === '' ? null : Member::query()->where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'found' => false,
                'message' => 'No customer found for that Member ID.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'status' => $member->status,
        ]);
    }

    public function preview(Request $request, PackagePurchaseService $purchases): JsonResponse
    {
        $memberId = strtoupper(trim((string) $request->query('member_id', '')));
        $member = $memberId === '' ? null : Member::query()->where('member_id', $memberId)->first();

        if (! $member) {
            return response()->json([
                'found' => false,
                'message' => 'No customer found for that Member ID.',
            ], 404);
        }

        $package = null;
        if ($request->filled('package_id')) {
            $package = Package::query()
                ->whereKey($request->query('package_id'))
                ->where('is_active', true)
                ->first();
            if (! $package) {
                return response()->json([
                    'found' => true,
                    'member' => $this->memberPayload($member),
                    'package' => null,
                    'message' => 'Choose an active package.',
                ], 422);
            }
        }

        $state = $purchases->customerPackageState($member, $package);
        $active = $state['active'];

        return response()->json([
            'found' => true,
            'member' => $this->memberPayload($member),
            'package' => $package ? [
                'id' => $package->id,
                'name' => $package->name,
                'code' => $package->code,
                'price' => \App\Support\MoneyDisplay::format($package->price_usd, $package->denomination),
                'type' => $package->typeLabel(),
                'roi_eligible' => $package->roi_eligible ? 'Eligible' : 'Not eligible',
                'level_eligible' => $package->level_commission_eligible ? 'Eligible' : 'Not eligible',
            ] : null,
            'blocked' => $active !== null,
            'message' => $active ? 'This customer already has an active purchase of this package.' : null,
            'active_purchase' => $this->purchasePayload($active),
            'pending_purchase' => $active ? null : $this->purchasePayload($state['pending']),
            'rejected_purchase' => $active || $state['pending'] ? null : $this->purchasePayload($state['rejected']),
        ]);
    }

    public function store(Request $request, PackagePurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'string', 'max:32'],
            'package_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::in(array_keys(PackagePurchase::PAYMENT_METHODS))],
            'payment_reference' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $member = Member::query()->where('member_id', strtoupper(trim($data['member_id'])))->first();
        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => 'No customer found for that Member ID.',
            ]);
        }

        $package = Package::query()->whereKey($data['package_id'])->first();
        if (! $package || ! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => 'Choose an active package.',
            ]);
        }

        $invoice = $purchases->recordForCustomer(
            $member,
            $package,
            $request->user(),
            $data['payment_method'],
            $data['payment_reference'],
            $data['idempotency_key'],
        );

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('success', 'Purchase recorded for '.$member->member_id.'. The invoice is the receipt. No wallet was credited and no income was generated.');
    }

    /**
     * @return array{member_id: string, member_name: string, status: string, status_label: string}
     */
    private function memberPayload(Member $member): array
    {
        return [
            'member_id' => $member->member_id,
            'member_name' => $member->member_name,
            'status' => $member->status,
            'status_label' => $member->status === 'active' ? 'Active' : 'Inactive',
        ];
    }

    /**
     * @return array{reference: string, status: string, activation: string, submitted_at: ?string, approved_at: ?string}|null
     */
    private function purchasePayload(?PackagePurchase $purchase): ?array
    {
        if (! $purchase) {
            return null;
        }

        $format = fn ($value): ?string => $value?->timezone('Asia/Kolkata')->format('d M Y H:i');

        return [
            'reference' => $purchase->reference,
            'status' => $purchase->paymentStatusLabel(),
            'activation' => $purchase->activationLabel(),
            'submitted_at' => $format($purchase->submitted_at),
            'approved_at' => $purchase->status === PackagePurchase::APPROVED ? $format($purchase->reviewed_at) : null,
        ];
    }
}
