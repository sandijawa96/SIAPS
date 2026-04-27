<?php

namespace App\Exports;

use App\Models\Kelas;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class KelasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $tahunAjaranId;

    public function __construct($tahunAjaranId = null)
    {
        $this->tahunAjaranId = $tahunAjaranId;
    }

    public function collection()
    {
        $query = Kelas::with(['tingkat', 'waliKelas', 'tahunAjaran', 'siswa']);

        if ($this->tahunAjaranId) {
            $query->where('tahun_ajaran_id', $this->tahunAjaranId);
        }

        return $query
            ->orderByRaw('COALESCE((SELECT urutan FROM tingkat WHERE tingkat.id = kelas.tingkat_id), 999999) asc')
            ->orderBy('nama_kelas', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Kelas',
            'Tingkat',
            'Jurusan',
            'Wali Kelas',
            'Tahun Ajaran',
            'Kapasitas',
            'Jumlah Siswa',
            'Status',
            'Keterangan'
        ];
    }

    public function map($kelas): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $kelas->nama_kelas,
            optional($kelas->tingkat)->nama,
            $kelas->jurusan ?: '-',
            optional($kelas->waliKelas)->nama_lengkap ?: 'Belum ditentukan',
            optional($kelas->tahunAjaran)->nama,
            $kelas->kapasitas,
            $kelas->siswa->where('pivot.status', 'aktif')->count(),
            $kelas->is_active ? 'Aktif' : 'Tidak Aktif',
            $kelas->keterangan ?: '-'
        ];
    }

    public function title(): string
    {
        return 'Data Kelas';
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();
        $range = "A1:{$lastColumn}{$lastRow}";

        // Style for header row
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '16A085'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style for all cells
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Center align specific columns
        $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
        $sheet->getStyle('G1:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Kapasitas & Jumlah Siswa
        $sheet->getStyle('I1:I' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Status

        // Set row height
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Auto-size columns
        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze the header row
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true],
            ],
        ];
    }
}
