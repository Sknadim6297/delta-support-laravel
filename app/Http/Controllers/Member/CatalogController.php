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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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

    public function show(Package $package, PackageIncomePolicy $policy): View
    {
        abort_unless($package->is_active, 404);

        return view('member.packages.show', [
            'package' => $package,
            'settings' => PaymentSetting::current(),
            'idempotencyKey' => (string) \Illuminate\Support\Str::uuid(),
            'incomeRule' => $policy->definition($package->code),
        ]);
    }

    public function purchase(Request $request, Package $package, PackagePurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $purchase = $purchases->submit($this->member($request), $package, $data['payment_reference'], $data['idempotency_key']);

        return redirect()->route('member.payments.show', $purchase)->with('success', 'Payment submitted and waiting for verification.');
    }

    public function mine(): View
    {
        $memberId = session('member_context_id');
        $purchases = PackagePurchase::query()
            ->with(['package', 'invoice'])
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
