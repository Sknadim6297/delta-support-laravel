<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; }
        h1 { font-size: 16px; margin: 0 0 8px; background: #101b2d; color: #fff; padding: 8px 10px; }
        p { margin: 0 0 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d5deea; padding: 3px 4px; text-align: left; vertical-align: top; }
        thead { display: table-header-group; }
        th { background: #101b2d; color: #fff; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>{{ $meta['title'] }}</h1>
    <p>Generated {{ $meta['generated'] }}</p>
    <p>Scope: {{ $meta['scope'] }}</p>
    @forelse ($meta['filters'] as $label => $value)
        <p>{{ $label }}: {{ $value }}</p>
    @empty
        <p>Filters: none</p>
    @endforelse
    <p>Amounts keep four decimal places. Currencies are listed in their own column and are not converted.</p>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">No records found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
