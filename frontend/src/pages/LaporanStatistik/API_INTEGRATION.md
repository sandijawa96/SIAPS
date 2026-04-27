# Integrasi API - Laporan Statistik

## Deskripsi
Dokumentasi ini menjelaskan integrasi halaman `Laporan & Statistik` dengan backend API Laravel untuk:
- laporan kehadiran harian/bulanan/tahunan,
- filter `tingkat -> kelas` (dependent) dan `status`,
- export Excel dan PDF.

## Endpoint API yang Digunakan

### 1. Attendance Report API
Base URL: `/api/reports/attendance`

#### Daily Report
- Endpoint: `GET /api/reports/attendance/daily`
- Parameter:
```json
{
  "tanggal": "YYYY-MM-DD",
  "kelas_id": 12,
  "tingkat_id": 3,
  "status": "hadir|terlambat|izin|sakit|alpha"
}
```

#### Monthly Report
- Endpoint: `GET /api/reports/attendance/monthly`
- Parameter:
```json
{
  "bulan": 1,
  "tahun": 2026,
  "kelas_id": 12,
  "tingkat_id": 3,
  "status": "hadir|terlambat|izin|sakit|alpha"
}
```

#### Yearly Report
- Endpoint: `GET /api/reports/attendance/yearly`
- Parameter:
```json
{
  "tahun": 2026,
  "kelas_id": 12,
  "tingkat_id": 3,
  "status": "hadir|terlambat|izin|sakit|alpha"
}
```

### 2. Export API
Base URL: `/api/reports/export`

#### Export Excel
- Endpoint: `GET /api/reports/export/excel`
- Parameter:
```json
{
  "start_date": "YYYY-MM-DD",
  "end_date": "YYYY-MM-DD",
  "kelas_id": 12,
  "tingkat_id": 3,
  "status": "hadir|terlambat|izin|sakit|alpha",
  "format": "xlsx|csv"
}
```
- Response: file binary (`.xlsx` / `.csv`)

#### Export PDF
- Endpoint: `GET /api/reports/export/pdf`
- Parameter:
```json
{
  "start_date": "YYYY-MM-DD",
  "end_date": "YYYY-MM-DD",
  "kelas_id": 12,
  "tingkat_id": 3,
  "status": "hadir|terlambat|izin|sakit|alpha"
}
```
- Response: file binary (`.pdf`)

### 3. Filter Master Data API

#### Tingkat
- Endpoint: `GET /api/tingkat?is_active=true`
- Tujuan: mengisi opsi filter tingkat.

#### Kelas (dependent by tingkat)
- Endpoint semua kelas: `GET /api/kelas`
- Endpoint kelas per tingkat: `GET /api/kelas/tingkat/{tingkatId}`
- Tujuan: saat tingkat dipilih, opsi kelas hanya dari tingkat tersebut.

## Service Layer

### `reportService.js`
```js
export const reportAPI = {
  getDailyReport: (params) => api.get('/reports/attendance/daily', { params }),
  getMonthlyReport: (params) => api.get('/reports/attendance/monthly', { params }),
  getYearlyReport: (params) => api.get('/reports/attendance/yearly', { params }),
  exportExcel: (params) => api.get('/reports/export/excel', { params, responseType: 'blob' }),
  exportPdf: (params) => api.get('/reports/export/pdf', { params, responseType: 'blob' }),
};
```

### `buildReportParams(filters)`
```js
export const buildReportParams = (filters) => {
  const params = {};

  if (filters.tanggalMulai) params.start_date = formatDateForAPI(filters.tanggalMulai);
  if (filters.tanggalSelesai) params.end_date = formatDateForAPI(filters.tanggalSelesai);

  if (filters.selectedTingkat && filters.selectedTingkat !== 'Semua') {
    params.tingkat_id = filters.selectedTingkat;
  }

  if (filters.selectedKelas && filters.selectedKelas !== 'Semua') {
    params.kelas_id = filters.selectedKelas;
  }

  if (filters.selectedStatus && filters.selectedStatus !== 'Semua') {
    params.status = String(filters.selectedStatus).toLowerCase();
  }

  return params;
};
```

## Custom Hook Integration

### `useLaporanStatistik.js`
State filter yang dipakai saat ini:
- `selectedTingkat`
- `selectedKelas`
- `selectedStatus`
- `tanggalMulai`
- `tanggalSelesai`
- `periode`

Catatan:
- Filter role tidak dipakai di halaman ini.
- Scope akses data tetap dikendalikan backend berdasarkan role/permission user yang login.

Alur fetch:
1. load `tingkat` aktif,
2. load `kelas` sesuai tingkat terpilih,
3. build params,
4. panggil endpoint sesuai periode (`daily/monthly/yearly`),
5. transform response ke tabel dan kartu statistik.

## Format Data Tabel Frontend
Kolom tabel laporan kehadiran saat ini:
- `Nama`
- `Kelas`
- `Hadir`
- `Terlambat (m)`
- `Izin`
- `Alpha (m)`
- `% Kehadiran`
- `Pelanggaran`
- `Status`

## Authentication dan Permission
- Semua request memakai Bearer token (`Authorization: Bearer <token>`).
- Endpoint report/export dilindungi permission `view_reports`.

## Troubleshooting Ringkas
1. `401 Unauthorized`: token invalid/expired.
2. `403 Forbidden`: user tidak punya `view_reports` atau akses kelas di luar scope yang diizinkan backend.
3. `422 Validation Error`: parameter tanggal/format/filter tidak valid.
4. Export gagal download: cek `responseType: 'blob'` di frontend dan header response backend.
