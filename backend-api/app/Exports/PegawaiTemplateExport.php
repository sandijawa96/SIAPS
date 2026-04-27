<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PegawaiTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes(): array
    {
        return [
            'PANDUAN PENGISIAN:',
            '- Jenis Kelamin: isi L atau P (boleh juga Laki-laki/Perempuan)',
            '- Role: isi role yang tersedia di sistem (contoh: Guru, Staff, Wali Kelas)',
            '- Sub Role: opsional, bisa diisi role tambahan',
            '- Status Kepegawaian: isi ASN atau Honorer',
            '- Status: isi Aktif atau Tidak Aktif',
            '- Password akan otomatis diset ke "ICTsmanis2025$"',
            '- Username harus unik (tidak boleh sama)',
            '- Email harus valid dan unik',
            '- Hapus contoh data sebelum upload',
            '',
            'Contoh Kombinasi Role:',
            '- Guru + Wali Kelas (Role: Guru, Sub Role: Wali Kelas)',
            '- Staff + Wali Kelas (Role: Staff, Sub Role: Wali Kelas)',
            '- Guru saja (Role: Guru, Sub Role: kosong)',
            '',
            'Catatan:',
            '- Template dibuat: ' . date('d/m/Y H:i'),
            '- Versi template: 1.2',
        ];
    }

    public function array(): array
    {
        return [
            ['guru01', 'guru1@sekolah.com', 'Ahmad Susanto, S.Pd', 'L', 'Guru', 'Wali Kelas', 'ASN', 'Aktif', '', ''],
            ['staff01', 'staff1@sekolah.com', 'Siti Aminah, S.Kom', 'P', 'Staff', '', 'Honorer', 'Aktif', '', ''],
            ['guru02', 'guru2@sekolah.com', 'Budi Santoso, S.Pd', 'L', 'Guru', '', 'ASN', 'Aktif', '', ''],
            ['', '', '', '', '', '', '', '', '', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'Username',
            'Email',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Role',
            'Sub Role',
            'Status Kepegawaian',
            'Status',
            '',
            'Keterangan'
        ];
    }

    public function title(): string
    {
        return 'Template Import Pegawai';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 30,
            'C' => 25,
            'D' => 12,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 12,
            'I' => 3,
            'J' => 40,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
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
            "A1:H{$lastRow}" => [
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
            "D1:D{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            "G1:G{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            "H1:H{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],
            "J1:J{$lastRow}" => [
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => '666666']
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'TEMPLATE IMPORT PEGAWAI');
                $sheet->mergeCells('A1:H1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '2F4F4F']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                $sheet->setCellValue('A2', 'Petunjuk: Isi data pegawai mulai dari baris 5. Hapus contoh data sebelum upload.');
                $sheet->mergeCells('A2:H2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '666666']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ]
                ]);

                $sheet->setCellValue('A3', '');

                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(10);
                $sheet->getRowDimension(4)->setRowHeight(25);

                $notes = $this->getNotes();
                foreach ($notes as $index => $note) {
                    $row = $index + 4;
                    $sheet->setCellValue("J{$row}", $note);

                    if ($note === 'PANDUAN PENGISIAN:' || $note === 'Catatan:' || $note === 'Contoh Kombinasi Role:') {
                        $sheet->getStyle("J{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '2F4F4F']
                            ]
                        ]);
                    } else {
                        $sheet->getStyle("J{$row}")->applyFromArray([
                            'font' => [
                                'size' => 10,
                                'color' => ['rgb' => '666666']
                            ]
                        ]);
                    }
                }

                $sheet->getColumnDimension('J')->setAutoSize(true);
            }
        ];
    }
}
