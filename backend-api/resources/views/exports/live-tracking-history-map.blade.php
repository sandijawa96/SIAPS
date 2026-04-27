<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Histori Peta Siswa' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        h1, h2, h3 {
            margin: 0;
        }
        .meta {
            margin-top: 8px;
            margin-bottom: 14px;
            font-size: 10px;
            color: #475569;
        }
        .callout {
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #dbeafe;
            border-radius: 8px;
            background: #eff6ff;
        }
        .grid {
            width: 100%;
        }
        .metric {
            display: inline-block;
            width: 23%;
            margin-right: 1%;
            margin-bottom: 10px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            box-sizing: border-box;
        }
        .metric strong {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            color: #0f172a;
        }
        .legend {
            margin: 12px 0;
        }
        .legend-item {
            display: inline-block;
            margin: 0 10px 8px 0;
            font-size: 10px;
            color: #334155;
        }
        .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            margin-right: 6px;
            vertical-align: middle;
        }
        .map-panel {
            margin-bottom: 14px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
        }
        .note {
            margin-top: 8px;
            font-size: 10px;
            color: #64748b;
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
            background: #f8fafc;
            font-weight: bold;
        }
        .small {
            font-size: 10px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <h2>{{ $title ?? 'Histori Peta Siswa' }}</h2>
    <div class="meta">
        <div>Generated: {{ $generatedAt ?? now()->format('Y-m-d H:i:s') }}</div>
        <div>Tanggal: {{ data_get($filters, 'date', '-') }}</div>
        <div>Rentang: {{ data_get($filters, 'start_time', '-') ?: '00:00' }} s/d {{ data_get($filters, 'end_time', '-') ?: '23:59' }}</div>
        <div>Mode ekspor: {{ ($exportScope ?? 'focus') === 'focus' ? 'Fokus saja' : 'Semua compare' }}</div>
    </div>

    <div class="callout">
        <strong>Catatan</strong>
        <div class="small" style="margin-top:4px;">
            Visual ini adalah skema jalur titik histori yang tersimpan, bukan screenshot tile peta. Jalur sudah disederhanakan maksimal {{ $routePointLimit ?? 120 }} titik per siswa agar PDF tetap ringan dan terbaca.
        </div>
    </div>

    <div class="grid">
        <div class="metric">
            <div class="small">Siswa dipilih</div>
            <strong>{{ data_get($summary, 'selected_students', 0) }}</strong>
        </div>
        <div class="metric">
            <div class="small">Siswa bertitik</div>
            <strong>{{ data_get($summary, 'students_with_points', 0) }}</strong>
        </div>
        <div class="metric">
            <div class="small">Total titik</div>
            <strong>{{ data_get($summary, 'total_points', 0) }}</strong>
        </div>
        <div class="metric">
            <div class="small">Estimasi jarak</div>
            <strong>{{ number_format((float) data_get($summary, 'estimated_distance_km', 0), 2) }} km</strong>
        </div>
    </div>

    @if (!empty($sessions))
        <div class="legend">
            @foreach (($figure['legend'] ?? []) as $legend)
                <span class="legend-item">
                    <span class="legend-swatch" style="background: {{ $legend['color'] }};"></span>
                    {{ $legend['name'] }}
                </span>
            @endforeach
        </div>
    @endif

    <div class="map-panel">
        @if (!empty($figure['has_data']))
            <svg width="100%" viewBox="0 0 {{ data_get($figure, 'width', 960) }} {{ data_get($figure, 'height', 420) }}" xmlns="http://www.w3.org/2000/svg">
                <rect x="0" y="0" width="{{ data_get($figure, 'width', 960) }}" height="{{ data_get($figure, 'height', 420) }}" rx="18" fill="#f8fafc" stroke="#cbd5e1" />
                @foreach (($figure['paths'] ?? []) as $path)
                    <path
                        d="{{ $path['path_d'] }}"
                        fill="none"
                        stroke="{{ $path['color'] }}"
                        stroke-width="{{ !empty($path['is_focus']) ? 5 : 3 }}"
                        stroke-opacity="{{ !empty($path['is_focus']) ? 0.95 : 0.65 }}"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                @endforeach
                @foreach (($figure['markers'] ?? []) as $marker)
                    <circle cx="{{ $marker['x'] }}" cy="{{ $marker['y'] }}" r="{{ !empty($marker['is_focus']) ? 9 : 7 }}" fill="{{ $marker['color'] }}" stroke="#ffffff" stroke-width="2" />
                    <text x="{{ $marker['x'] }}" y="{{ $marker['y'] + 3.5 }}" text-anchor="middle" font-size="{{ !empty($marker['is_focus']) ? 8 : 7 }}" font-weight="bold" fill="#ffffff">{{ $marker['label'] }}</text>
                @endforeach
            </svg>
        @else
            <div class="small">Tidak ada titik histori pada filter ini.</div>
        @endif

        <div class="note">
            Fokus: {{ data_get($focusSession, 'user.nama_lengkap', '-') }}.
            @if (data_get($focusSession, 'statistics.is_route_simplified'))
                Jalur fokus disederhanakan ke {{ data_get($focusSession, 'statistics.map_point_count', 0) }} dari {{ data_get($focusSession, 'statistics.total_points', 0) }} titik.
            @else
                Jalur fokus menggunakan seluruh titik tersimpan.
            @endif
        </div>
    </div>

    @if (!empty($focusSession))
        <h3>Ringkasan Fokus</h3>
        <table style="margin-top:8px; margin-bottom:14px;">
            <tbody>
                <tr>
                    <th style="width:20%;">Nama</th>
                    <td>{{ data_get($focusSession, 'user.nama_lengkap', '-') }}</td>
                    <th style="width:20%;">Kelas</th>
                    <td>{{ data_get($focusSession, 'user.kelas', '-') }}</td>
                </tr>
                <tr>
                    <th>Tingkat</th>
                    <td>{{ data_get($focusSession, 'user.tingkat', '-') }}</td>
                    <th>Wali Kelas</th>
                    <td>{{ data_get($focusSession, 'user.wali_kelas', '-') }}</td>
                </tr>
                <tr>
                    <th>Total titik</th>
                    <td>{{ data_get($focusSession, 'statistics.total_points', 0) }}</td>
                    <th>Jarak</th>
                    <td>{{ number_format((float) data_get($focusSession, 'statistics.estimated_distance_km', 0), 2) }} km</td>
                </tr>
                <tr>
                    <th>Mulai</th>
                    <td>{{ data_get($focusSession, 'statistics.started_at', '-') }}</td>
                    <th>Selesai</th>
                    <td>{{ data_get($focusSession, 'statistics.ended_at', '-') }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h3>Timeline Fokus</h3>
    <table style="margin-top:8px;">
        <thead>
            <tr>
                <th style="width:8%;">#</th>
                <th style="width:20%;">Waktu</th>
                <th>Lokasi</th>
                <th style="width:12%;">Status</th>
                <th style="width:15%;">Segmen</th>
                <th style="width:15%;">Kumulatif</th>
            </tr>
        </thead>
        <tbody>
            @forelse ((array) data_get($focusSession, 'points', []) as $point)
                <tr>
                    <td>{{ data_get($point, 'sequence', '-') }}</td>
                    <td>{{ data_get($point, 'tracked_at', '-') }}</td>
                    <td>
                        {{ data_get($point, 'location_name', '-') }}
                        @if (!empty($point['transition']))
                            <div class="small">{{ $point['transition'] === 'enter_area' ? 'Masuk area absensi' : 'Keluar area absensi' }}</div>
                        @endif
                    </td>
                    <td>{{ !empty($point['is_in_school_area']) ? 'Dalam area' : 'Luar area' }}</td>
                    <td>{{ number_format((float) data_get($point, 'distance_from_previous_meters', 0), 1) }} m</td>
                    <td>{{ number_format((float) data_get($point, 'cumulative_distance_meters', 0), 1) }} m</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada titik histori pada rentang ini</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
