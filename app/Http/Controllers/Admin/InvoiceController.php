<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('invoice_number', 'like', $term)
                        ->orWhere('member_id', 'like', $term)
                        ->orWhere('package_name', 'like', $term);
                });
            })
            ->latest('approved_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice): View
    {
        return view('admin.invoices.show', compact('invoice'));
    }

    public function pdf(Invoice $invoice): Response
    {
        return response(SimplePdf::document($this->lines($invoice)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public static function lines(Invoice $invoice): array
    {
        return [
            (string) ($invoice->company_name ?: 'Company name not set'),
            (string) ($invoice->company_address ?: ''),
            trim(((string) $invoice->company_email) . ' ' . ((string) $invoice->company_phone)),
            '',
            'Invoice ' . $invoice->invoice_number,
            'Member ' . $invoice->member_name . ' (' . $invoice->member_id . ')',
            'Package ' . $invoice->package_name,
            'Purchase date ' . optional($invoice->purchased_at)->timezone('Asia/Kolkata')->format('d M Y H:i'),
            'Approval date ' . optional($invoice->approved_at)->timezone('Asia/Kolkata')->format('d M Y H:i'),
            'Amount USD ' . $invoice->price_usd,
            'Payment ' . trim(((string) $invoice->currency_label) . ' ' . ((string) $invoice->network)),
            'Reference ' . $invoice->payment_reference,
            'Status ' . $invoice->status,
        ];
    }
}
