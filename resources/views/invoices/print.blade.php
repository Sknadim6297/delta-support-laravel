<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    @vite(['resources/css/panel.css'])
    <style>
        body { margin: 0; background: #f4f7fb; color: #092747; }
        .print-bar { display: flex; justify-content: flex-end; gap: 8px; padding: 16px; }
        @media print {
            body { background: #fff; }
            .print-bar { display: none; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button type="button" onclick="window.print()" style="background:#0878d1;color:#fff;border:0;border-radius:8px;padding:8px 14px;font-weight:600;">Print</button>
    </div>
    <x-invoice.document :invoice="$invoice" />
</body>
</html>
