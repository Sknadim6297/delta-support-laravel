<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackagePurchase;
use App\Models\PaymentSetting;
use App\Services\PackagePurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentReviewController extends Controller
{
    public function purchases(Request $request): View
    {
        $purchases = PackagePurchase::query()
            ->with('invoice')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('member_id', 'like', $term)
                        ->orWhere('payment_reference', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('package_name', 'like', $term);
                });
            })
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();
        $status = 'all';

        return view('admin.payments.index', compact('purchases', 'status'));
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', PackagePurchase::PENDING);
        abort_unless(in_array($status, [PackagePurchase::PENDING, PackagePurchase::APPROVED, PackagePurchase::REJECTED], true), 404);

        $purchases = PackagePurchase::query()
            ->with('invoice')
            ->where('status', $status)
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('member_id', 'like', $term)
                        ->orWhere('payment_reference', 'like', $term)
                        ->orWhere('reference', 'like', $term);
                });
            })
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', compact('purchases', 'status'));
    }

    public function settings(): View
    {
        return view('admin.payments.settings', ['settings' => PaymentSetting::current()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency_label' => ['nullable', 'string', 'max:40'],
            'network' => ['nullable', 'string', 'max:80'],
            'receiving_address' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'reference_pattern' => ['nullable', 'string', 'max:120'],
            'qr_image' => ['nullable', 'image', 'max:2048'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        $pattern = trim((string) ($data['reference_pattern'] ?? ''));
        if ($pattern !== '' && @preg_match('/' . str_replace('/', '\/', $pattern) . '/', '') === false) {
            throw ValidationException::withMessages([
                'reference_pattern' => 'Enter a valid reference pattern.',
            ]);
        }

        $settings = PaymentSetting::current();
        $settings->fill([
            'currency_label' => $data['currency_label'] ?? null,
            'network' => $data['network'] ?? null,
            'receiving_address' => $data['receiving_address'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'reference_pattern' => $pattern !== '' ? $pattern : null,
            'is_enabled' => $request->boolean('is_enabled'),
        ])->save();

        if ($request->hasFile('qr_image')) {
            if ($settings->qr_image_path) {
                Storage::disk('local')->delete($settings->qr_image_path);
            }
            $settings->forceFill([
                'qr_image_path' => $request->file('qr_image')->store('payment-qr', 'local'),
            ])->save();
        }

        return redirect()->route('admin.payments.settings')->with('success', 'Payment settings saved. Members are not approved automatically.');
    }

    public function qr()
    {
        $settings = PaymentSetting::current();
        abort_unless($settings->qr_image_path && Storage::disk('local')->exists($settings->qr_image_path), 404);

        return response()->file(Storage::disk('local')->path($settings->qr_image_path));
    }

    public function approve(Request $request, PackagePurchase $purchase, PackagePurchaseService $purchases): RedirectResponse
    {
        $invoice = $purchases->approve($purchase, $request->user());

        return redirect()->route('admin.invoices.show', $invoice)->with('success', 'Payment approved and invoice ' . $invoice->invoice_number . ' created.');
    }

    public function reject(Request $request, PackagePurchase $purchase, PackagePurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $purchases->reject($purchase, $request->user(), $data['reason']);

        return redirect()->route('admin.payments.index', ['status' => PackagePurchase::REJECTED])->with('success', 'Payment rejected.');
    }
}
