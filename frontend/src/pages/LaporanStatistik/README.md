# Laporan Statistik - Komponen Baru

## Deskripsi

Halaman Laporan Statistik yang telah diperbarui menggunakan Material-UI (MUI), Tailwind CSS, dan Lucide React dengan arsitektur komponen yang modular dan rapi.

## Struktur Komponen

```
LaporanStatistik/
├── index.jsx                    # Komponen utama
├── components/
│   ├── FilterSection.jsx        # Bagian filter laporan
│   ├── StatisticsCards.jsx      # Kartu statistik
│   ├── ReportTable.jsx          # Tabel data laporan
│   └── ExportActions.jsx        # Tombol export
├── hooks/
│   └── useLaporanStatistik.js   # Custom hook untuk logic
└── README.md                    # Dokumentasi ini
```

## Fitur Utama

### 1. FilterSection.jsx

- **Teknologi**: MUI FormControl, Select, TextField
- **Fitur**:
  - Filter berdasarkan periode, tanggal, role, status, dan kelas
  - Animasi dengan Framer Motion
  - Responsive design dengan Grid system
  - Tombol generate laporan

### 2. StatisticsCards.jsx

- **Teknologi**: MUI Paper, Typography, Grid
- **Fitur**:
  - 5 kartu statistik dengan warna berbeda
  - Loading skeleton saat data dimuat
  - Animasi hover dan entrance
  - Icon dari Lucide React

### 3. ReportTable.jsx

- **Teknologi**: MUI Table, Chip, Skeleton
- **Fitur**:
  - Tabel responsif dengan data kehadiran
  - Color coding untuk persentase kehadiran
  - Chip untuk role dan status
  - Loading state dengan skeleton
  - Empty state handling

### 4. ExportActions.jsx

- **Teknologi**: MUI Button, Tooltip
- **Fitur**:
  - Tombol export Excel dan PDF
  - Animasi hover dan click
  - Tooltip informatif

### 5. useLaporanStatistik.js

- **Custom Hook** untuk:
  - State management
  - Data fetching simulation
  - Statistics calculation
  - Event handlers

## Teknologi yang Digunakan

- **React 18** - Framework utama
- **Material-UI v5** - Komponen UI
- **Tailwind CSS** - Utility-first CSS
- **Lucide React** - Icon library
- **Framer Motion** - Animasi
- **React Hooks** - State management

## Peningkatan dari Versi Sebelumnya

1. **Modularitas**: Komponen dipecah menjadi bagian-bagian kecil yang reusable
2. **UI/UX**: Menggunakan MUI untuk konsistensi design system
3. **Performance**: Optimasi dengan useMemo, useCallback, dan lazy loading
4. **Accessibility**: Komponen MUI sudah accessible by default
5. **Animasi**: Smooth transitions dengan Framer Motion
6. **Responsive**: Better responsive design dengan MUI Grid
7. **Loading States**: Skeleton loading untuk better UX
8. **API Integration**: Terhubung dengan backend Laravel API
9. **Real Data**: Menggunakan data real dari database
10. **Dynamic Filters**: Filter kelas yang dinamis dari API
11. **Export Functionality**: Integrasi dengan backend untuk export Excel/PDF
12. **Error Handling**: Penanganan error yang lebih baik

## Cara Penggunaan

```jsx
import LaporanStatistik from "./pages/LaporanStatistik";

function App() {
  return <LaporanStatistik />;
}
```

## Customization

### Mengubah Warna Statistik Cards

Edit file `StatisticsCards.jsx` pada bagian `colorClasses`:

```jsx
const colorClasses = {
  green: {
    bg: "bg-green-50",
    icon: "bg-green-500",
    text: "text-green-600",
  },
  // Tambah warna baru...
};
```

### Menambah Filter Baru

Edit file `FilterSection.jsx` dan tambahkan FormControl baru dalam Grid container.

### Mengubah Kolom Tabel

Edit file `ReportTable.jsx` pada bagian TableHead dan TableBody.

## Dependencies

Pastikan dependencies berikut sudah terinstall:

```json
{
  "@mui/material": "^5.17.1",
  "@mui/icons-material": "^5.17.1",
  "@emotion/react": "^11.14.0",
  "@emotion/styled": "^11.14.1",
  "framer-motion": "^12.18.1",
  "lucide-react": "^0.220.0"
}
```

## Performance Tips

1. **Lazy Loading**: Komponen sudah menggunakan lazy loading untuk data
2. **Memoization**: useMemo dan useCallback digunakan untuk optimasi
3. **Skeleton Loading**: Mengurangi perceived loading time
4. **Efficient Re-renders**: State management yang optimal

## Future Enhancements

1. **Real API Integration**: Mengganti mock data dengan API calls
2. **Advanced Filtering**: Filter yang lebih kompleks
3. **Data Visualization**: Menambah chart dan grafik
4. **Export Functionality**: Implementasi real export ke Excel/PDF
5. **Pagination**: Untuk dataset yang besar
6. **Search**: Fitur pencarian dalam tabel
