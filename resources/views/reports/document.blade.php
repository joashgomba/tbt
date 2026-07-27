<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $payload['report']['title'] ?? 'NTB Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; }
        .kpi-grid { display: table; width: 100%; margin: 12px 0; }
        .kpi { display: table-cell; width: 25%; padding: 8px; border: 1px solid #e2e8f0; }
        .kpi strong { display: block; font-size: 16px; }
        .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>{{ $payload['report']['title'] ?? 'Report' }}</h1>
    <div class="meta">
        Report ID: {{ $payload['report']['code'] ?? '—' }} ·
        Generated: {{ $payload['report']['generated_at'] ?? now()->toIso8601String() }} ·
        Classification: {{ $payload['report']['confidentiality'] ?? 'internal' }}
    </div>

    @if(!empty($payload['summary']))
        <div class="kpi-grid">
            @foreach(collect($payload['summary'])->take(8) as $key => $value)
                <div class="kpi">
                    <span>{{ str_replace('_', ' ', $key) }}</span>
                    <strong>{{ is_scalar($value) ? $value : json_encode($value) }}</strong>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($payload['rows']))
        <table>
            <thead>
                <tr>
                    @foreach(array_keys((array) ($payload['rows'][0] ?? [])) as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($payload['rows'] as $row)
                    <tr>
                        @foreach((array) $row as $cell)
                            <td>{{ is_scalar($cell) ? $cell : json_encode($cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No matching records for the selected filters.</p>
    @endif

    <div class="footer">
        NTB Portal · Official reporting document · Data cut-off reflected in generation timestamp.
    </div>
</body>
</html>
