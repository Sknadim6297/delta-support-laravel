<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/panel.css'])
    @include('partials.brand-tokens')
</head>
<body class="ds-print">
    <div class="ds-print-bar">
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <x-invoice.document :invoice="$invoice" />
</body>
</html>
