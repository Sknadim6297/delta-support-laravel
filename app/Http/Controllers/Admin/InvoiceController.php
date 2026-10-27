<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\QueryDate;
use App\Support\SimplePdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $from = $this->queryDate($request, 'from');
        $to = $this->queryDate($request, 'to');
        $status = $request->query('status');
        $status = in_array($status, ['approved'], true) ? $status : null;

        $invoices = Invoice::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . trim((string) $request->query('q')) . '%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('invoice_number', 'like', $term)
                        ->orWhere('member_id', 'like', $term)
                        ->orWhere('package_name', 'like', $term)
                        ->orWhere('package_code', 'like', $term);
                });
            })
            ->when($from, fn ($query) => $query->whereDate('approved_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('approved_at', '<=', $to))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('approved_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice): View
    {
        return view('admin.invoices.show', compact('invoice'));
    }

    public function print(Invoice $invoice): View
    {
        return view('invoices.print', compact('invoice'));
    }

    public function pdf(Invoice $invoice): Response
    {
        return $this->download($invoice);
    }

    public static function download(Invoice $invoice): Response
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '', $invoice->invoice_number) ?: 'invoice';

        return response(SimplePdf::invoice($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }

    private function queryDate(Request $request, string $key): ?string
    {
        return QueryDate::day($request->query($key))?->toDateString();
    }
}
