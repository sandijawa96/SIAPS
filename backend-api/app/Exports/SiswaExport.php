<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SiswaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    private $rowNumber = 0;

    public function collection()
    {
        return User::role('Siswa')
            ->with(['dataPribadiSiswa', 'kelas' => function ($query) {
                $query->withPivot('tanggal_masuk', 'tahun_ajaran_id', 'status', 'is_active');
            }])
            ->orderBy('nama_lengkap')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Username',
            'Email',
            'Nama Lengkap',
            'NIS',
            'NISN',
            'Tanggal Lahir',
            'Jenis Kelamin',
            'Kelas',
            'Tahun Ajaran',
            'Tanggal Masuk',
            'No. Telepon Orang Tua',
            'Status'
        ];
    }

    public function map($siswa): array
    {
        $this->rowNumber++;

        // Get kelas aktif untuk siswa dengan pivot data
        $kelasAktif = null;
        $tanggalMasuk = '';
        $tahunAjaranNama = '';

        foreach ($siswa->kelas as $kelas) {
            if ($kelas->pivot->is_active == 1) {
                $kelasAktif = $kelas;
                $tanggalMasuk = $kelas->pivot->tanggal_masuk ?? '';

                // Get tahun ajaran name
                $tahunAjaran = \App\Models\TahunAjaran::find($kelas->pivot->tahun_ajaran_id);
                $tahunAjaranNama = $tahunAjaran ? $tahunAjaran->nama : '';
                break;
            }
        }

        return [
            $this->rowNumber,
            $siswa->username,
            $siswa->email,
            $siswa->nama_lengkap,
            $siswa->nis,
            $siswa->nisn,
            $siswa->dataPribadiSiswa?->tanggal_lahir?->format('Y-m-d') ?? '',
            $siswa->dataPribadiSiswa?->jenis_kelamin ?? '',
            $kelasAktif?->nama_kelas ?? '',
            $tahunAjaranNama,
            $tanggalMasuk,
            $siswa->dataPribadiSiswa?->no_hp_ayah ?? $siswa->dataPribadiSiswa?->no_hp_ibu ?? '',
            $siswa->is_active ? 'Aktif' : 'Tidak Aktif'
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 20,  // Username
            'C' => 30,  // Email
            'D' => 30,  // Nama Lengkap
            'E' => 15,  // NIS
            'F' => 15,  // NISN
            'G' => 15,  // Tanggal Lahir
            'H' => 15,  // Jenis Kelamin
            'I' => 20,  // Kelas
            'J' => 20,  // Tahun Ajaran
            'K' => 15,  // Tanggal Masuk
            'L' => 20,  // No. Telepon Orang Tua
            'M' => 12,  // Status
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = 'M';
        $lastRow = $sheet->getHighestRow();

        return [
            // Header style
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '16A085']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ],

            // All cells border and alignment
            "A1:{$lastColumn}{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],

            // Data rows
            "A2:{$lastColumn}{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                ],
            ],

            // Center align for specific columns
            "A1:A{$lastRow}" => [ // No
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            "E2:F{$lastRow}" => [ // NIS, NISN
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "G2:H{$lastRow}" => [ // Tanggal Lahir, Jenis Kelamin
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "K2:K{$lastRow}" => [ // Tanggal Masuk
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "M2:M{$lastRow}" => [ // Status
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }
}
