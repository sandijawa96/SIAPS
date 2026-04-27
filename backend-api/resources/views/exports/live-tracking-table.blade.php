<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Laporan Live Tracking' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        .meta {
            margin-bottom: 10px;
            font-size: 10px;
            color: #4b5563;
        }
        .callout {
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
        }
        .callout h3 {
            margin: 0 0 6px;
            font-size: 11px;
        }
        .callout ul {
            margin: 0;
            padding-left: 18px;
        }
        .chips {
            margin: 8px 0 12px;
        }
        .chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 4px 8px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #f8fafc;
            font-size: 10px;
            color: #334155;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Laporan Live Tracking' }}</h2>
    <div class="meta">
        <div>Periode: {{ $period ?? '-' }}</div>
        <div>Generated: {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
    </div>

    @if (!empty($context['notes'] ?? []))
        <div class="callout">
            <h3>Catatan Histori</h3>
            <ul>
                @foreach (($context['notes'] ?? []) as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($context['runtime_summary'] ?? null))
        <div class="meta">
            <div><strong>Policy Histori:</strong> {{ $context['runtime_summary'] }}</div>
        </div>
    @endif

    @if (!empty($context['filters'] ?? []))
        <div class="chips">
            @foreach (($context['filters'] ?? []) as $filter)
                <span class="chip">{{ $filter }}</span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach (($headers ?? []) as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse (($rows ?? []) as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers ?? []) }}">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
