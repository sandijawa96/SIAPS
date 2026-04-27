<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 16px 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.32;
        }

        .header {
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f766e;
        }

        .subtitle {
            margin: 3px 0 0;
            font-size: 10px;
            color: #4b5563;
            font-weight: 500;
        }

        .meta {
            margin-top: 7px;
            font-size: 9px;
            color: #4b5563;
        }

        .meta div {
            margin-bottom: 2px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.report thead th {
            background: #16a085;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            text-align: left;
            padding: 5px 4px;
            border: 1px solid #0f766e;
        }

        table.report tbody td {
            border: 1px solid #d1d5db;
            padding: 4px;
            vertical-align: top;
            word-break: break-word;
        }

        table.report tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .empty {
            text-align: center;
            font-style: italic;
            color: #6b7280;
            padding: 12px 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">{{ $title ?? 'Laporan Data' }}</h1>
        <p class="subtitle">{{ $subtitle ?? '' }}</p>

        <div class="meta">
            @if(!empty($disciplineLimitSummary))
                <div>Batas Disiplin Siswa: {{ $disciplineLimitSummary }}</div>
            @endif
            <div>Generated: {{ $generatedAt ?? '-' }} | By: {{ $generatedBy ?? '-' }}</div>
            <div>Filter: {{ $filterSummary ?? 'Semua data' }}</div>
        </div>
    </div>

    <table class="report">
        <thead>
            <tr>
                @foreach(($columns ?? []) as $column)
                    @php
                        $key = (string) ($column['key'] ?? '');
                        $centerKeys = ['no', 'tanggal', 'hadir', 'terlambat', 'tap', 'izin', 'sakit', 'alpha', 'persentase_kehadiran', 'pelanggaran', 'status_batas'];
                        $align = in_array($key, $centerKeys, true) ? 'center' : 'left';
                    @endphp
                    <th style="text-align: {{ $align }};">{{ $column['label'] ?? ($column['key'] ?? '-') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse(($rows ?? []) as $row)
                <tr>
                    @foreach(($columns ?? []) as $column)
                        @php
                            $key = (string) ($column['key'] ?? '');
                            $centerKeys = ['no', 'tanggal', 'hadir', 'terlambat', 'tap', 'izin', 'sakit', 'alpha', 'persentase_kehadiran', 'pelanggaran', 'status_batas'];
                            $align = in_array($key, $centerKeys, true) ? 'center' : 'left';
                            $value = data_get($row, $key);
                            if (is_bool($value)) {
                                $value = $value ? 'Ya' : 'Tidak';
                            }
                        @endphp
                        <td style="text-align: {{ $align }};">{{ ($value === null || $value === '') ? '-' : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ max(1, count($columns ?? [])) }}">Tidak ada data untuk diexport</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
