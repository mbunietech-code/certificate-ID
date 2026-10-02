<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: helvetica, sans-serif; font-size: 8.5pt; color: #111; }
        h1 { font-size: 13pt; margin: 0 0 2mm; }
        .meta { color: #555; font-size: 8pt; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e2e8f0; text-align: left; padding: 1.5mm 2mm; font-size: 8pt; border: 0.2mm solid #cbd5e1; }
        td { padding: 1.2mm 2mm; border: 0.2mm solid #e2e8f0; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ setting('system_name') }} · Generated {{ now()->format('d/m/Y H:i') }} · {{ count($rows) }} row(s){{ $truncated ? ' (truncated – use CSV/Excel for the full data)' : '' }}</div>
    <table>
        <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
