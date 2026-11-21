<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Services\PackageIncomePolicy;
use App\Services\PackagePurchaseService;
use App\Services\WorkingWalletFunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    public function explore(): View
    {
        $packages = Package::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('member.packages.explore', [
            'packages' => $packages,
            'settings' => PaymentSetting::current(),
        ]);
    }

    public function show(Package $package, PackageIncomePolicy $policy, WorkingWalletFunds $funds): View
    {
        abort_unless($package->is_active, 404);

        $member = Member::query()->where('member_id', session('member_context_id'))->first();

        return view('member.packages.show', [
            'package' => $package,
            'settings' => PaymentSetting::current(),
            'externalIdempotencyKey' => (string) \Illuminate\Support\Str::uuid(),
            'walletIdempotencyKey' => (string) \Illuminate\Support\Str::uuid(),
            'incomeRule' => $policy->definition($package->code),
            'workingAvailable' => $member ? $funds->available($member) : '0.0000',
            'workingStored' => $member ? $funds->balance($member) : '0.0000',
            'workingReserved' => $member ? $funds->reserved($member) : '0.0000',
        ]);
    }

    public function purchase(Request $request, Package $package, PackagePurchaseService $purchases): RedirectResponse
    {
        $request->merge([
            'funding_source' => in_array((string) $request->input('funding_source'), ['external', 'working_wallet'], true)
                ? (string) $request->input('funding_source')
                : 'external',
        ]);
        $data = $request->validate([
            'funding_source' => ['sometimes', 'in:external,working_wallet'],
            'funding_wallet' => ['exclude_if:funding_source,external', 'required_if:funding_source,working_wallet', 'in:working'],
            'payment_reference' => ['exclude_if:funding_source,working_wallet', 'required', 'string', 'max:255'],
            'external_idempotency_key' => ['exclude_if:funding_source,working_wallet', 'nullable', 'uuid'],
            'idempotency_key' => ['exclude_if:funding_source,working_wallet', 'nullable', 'uuid'],
            'wallet_idempotency_key' => ['exclude_if:funding_source,external', 'required', 'uuid'],
        ]);

        $fundingSource = $data['funding_source'] ?? 'external';
        if ($fundingSource === 'external') {
            $externalKey = $data['external_idempotency_key'] ?? $data['idempotency_key'] ?? null;
            if (! is_string($externalKey) || $externalKey === '') {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Submit the purchase once with a valid idempotency key.',
                ]);
            }
        }
        $purchase = $fundingSource === 'working_wallet'
            ? $purchases->submitWallet($this->member($request), $package, $data['wallet_idempotency_key'])
            : $purchases->submit($this->member($request), $package, $data['payment_reference'], $externalKey);

        $message = $fundingSource === 'working_wallet'
            ? 'Working Wallet funds reserved. Purchase is pending administrator approval.'
            : 'Payment submitted and waiting for verification.';

        return redirect()->route('member.payments.show', $purchase)->with('success', $message);
    }

    public function mine(): View
    {
        $memberId = session('member_context_id');
        $purchases = PackagePurchase::query()
            ->with(['package', 'invoice', 'walletReservation'])
            ->where('member_id', $memberId)
            ->latest('submitted_at')
            ->paginate(15);

        return view('member.packages.mine', compact('purchases'));
    }

    public function payments(): View
    {
        $purchases = PackagePurchase::query()
            ->with('invoice')
            ->where('member_id', session('member_context_id'))
            ->latest('submitted_at')
            ->paginate(15);

        return view('member.payments.index', compact('purchases'));
    }

    public function payment(Request $request, PackagePurchase $purchase): View
    {
        $this->owns($request, $purchase);
        $purchase->load('walletReservation');

        return view('member.payments.show', [
            'purchase' => $purchase,
            'settings' => PaymentSetting::current(),
        ]);
    }

    public function qr()
    {
        $settings = PaymentSetting::current();
        abort_unless(
            $settings->isReady() && $settings->qr_image_path && Storage::disk('local')->exists($settings->qr_image_path),
            404
        );

        return response()->file(Storage::disk('local')->path($settings->qr_image_path));
    }

    public function packageImage(Request $request, Package $package)
    {
        $owned = PackagePurchase::query()
            ->where('package_id', $package->id)
            ->where('member_id', $request->session()->get('member_context_id'))
            ->exists();

        abort_unless(
            ($package->is_active || $owned)
                && $package->image_path
                && $this->isPackageImagePath($package->image_path)
                && Storage::disk('local')->exists($package->image_path),
            404
        );

        return response()->file(Storage::disk('local')->path($package->image_path));
    }

    private function isPackageImagePath(string $path): bool
    {
        return (str_starts_with($path, 'packages/') || str_starts_with($path, 'package-images/'))
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/');
    }

    public function invoices(): View
    {
        $invoices = Invoice::query()->where('member_id', session('member_context_id'))->latest('approved_at')->paginate(15);

        return view('member.invoices.index', compact('invoices'));
    }

    public function invoice(Request $request, Invoice $invoice): View
    {
        $this->ownsInvoice($request, $invoice);

        return view('member.invoices.show', compact('invoice'));
    }

    public function invoicePrint(Request $request, Invoice $invoice): View
    {
        $this->ownsInvoice($request, $invoice);

        return view('invoices.print', compact('invoice'));
    }

    public function invoicePdf(Request $request, Invoice $invoice): Response
    {
        $this->ownsInvoice($request, $invoice);

        return InvoiceController::download($invoice);
    }

    public function referral(Request $request): View
    {
        $member = $this->member($request);

        return view('member.network.referral', [
            'link' => route('member.register', ['sponsor_id' => $member->member_id]),
            'member' => $member,
            'directCount' => Member::query()->where('sponsor_id', $member->member_id)->count(),
        ]);
    }

    public function tours(): RedirectResponse
    {
        return redirect()->route('member.packages.mine');
    }

    public function support(): View
    {
        return view('member.support.index');
    }

    private function member(Request $request): Member
    {
        return Member::query()->where('member_id', $request->session()->get('member_context_id'))->firstOrFail();
    }

    private function owns(Request $request, PackagePurchase $purchase): void
    {
        abort_unless($purchase->member_id === $request->session()->get('member_context_id'), 403);
    }

    private function ownsInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->member_id === $request->session()->get('member_context_id'), 403);
    }
}
