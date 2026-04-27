<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Histori Alert Pelanggaran</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 8px 0;
        }
        .meta {
            font-size: 10px;
            color: #4b5563;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
            font-weight: bold;
        }
        .small {
            font-size: 10px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <h1>Histori Alert Pelanggaran</h1>
    <div class="meta">
        Digenerate: {{ $generatedAt->format('d M Y H:i:s') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Indikator</th>
                <th>Siswa</th>
                <th>Kelas</th>
                <th>Periode</th>
                <th>Nilai</th>
                <th>Batas</th>
                <th>Status</th>
                <th>Kontak Ortu</th>
                <th>Broadcast Terakhir</th>
                <th>Trigger Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['rule_label'] ?? '-' }}</td>
                    <td>
                        {{ $row['student']['name'] ?? '-' }}
                        <div class="small">
                            NIS: {{ $row['student']['nis'] ?? '-' }} |
                            NISN: {{ $row['student']['nisn'] ?? '-' }}
                        </div>
                    </td>
                    <td>{{ $row['kelas']['name'] ?? '-' }}</td>
                    <td>
                        {{ $row['period_label'] ?? '-' }}<br>
                        {{ $row['tahun_ajaran_ref'] ?? '-' }}
                    </td>
                    <td>{{ $row['metric_value'] ?? 0 }} {{ $row['metric_unit'] ?? '' }}</td>
                    <td>{{ $row['metric_limit'] ?? 0 }} {{ $row['metric_unit'] ?? '' }}</td>
                    <td>{{ $row['status'] ?? '-' }}</td>
                    <td>{{ ($row['parent_phone_available'] ?? false) ? 'Tersedia' : 'Belum tersedia' }}</td>
                    <td>{{ $row['broadcast_campaign']['title'] ?? '-' }}</td>
                    <td>{{ $row['last_triggered_at'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">Belum ada data histori alert pelanggaran.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
