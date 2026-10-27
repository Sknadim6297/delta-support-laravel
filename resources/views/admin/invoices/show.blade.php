@extends('admin.layouts.app')

@section('title', 'Invoice '.$invoice->invoice_number)
@section('page-title', 'Invoice '.$invoice->invoice_number)

@section('content')
<div class="ds-catalog">
    <x-ui.page-header kicker="Invoices" :title="'Invoice '.$invoice->invoice_number">
        <x-slot:actions>
            <x-ui.button href="{{ route('admin.invoices.print', $invoice) }}" variant="secondary">Print</x-ui.button>
            <x-ui.button href="{{ route('admin.invoices.pdf', $invoice) }}">Download PDF</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    <x-invoice.document :invoice="$invoice" />
</div>
@endsection
