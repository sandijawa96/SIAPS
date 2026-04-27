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

class KelasTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes()
    {
        return [
            'PANDUAN PENGISIAN:',
            '• Nama Kelas*: Nama kelas lengkap (contoh: X IPA 1)',
            '• Tingkat*: Nama atau kode tingkat yang tersedia di sistem',
            '• Jurusan: Nama jurusan (opsional)',
            '• Wali Kelas: Nama lengkap atau NIP wali kelas (opsional)',
            '• Tahun Ajaran*: Format tahun ajaran yang tersedia (2024/2025)',
            '• Kapasitas*: Jumlah maksimal siswa dalam kelas',
            '• Keterangan: Informasi tambahan (opsional)',
            '• Status: Aktif atau Tidak Aktif (default: Aktif)',
            '',
            'Contoh Data:',
            '• Nama Kelas: X IPA 1, Tingkat: X, Jurusan: IPA',
            '• Kapasitas: 32, Status: Aktif',
            '',
            'Catatan:',
            '• Tanda * menunjukkan field wajib diisi',
            '• Tingkat harus sesuai dengan data di sistem',
            '• Tahun ajaran harus aktif di sistem',
            '• Wali kelas harus terdaftar sebagai guru/pegawai',
            '',
            'Template dibuat: ' . date('d/m/Y H:i'),
            'Versi template: 1.0',
        ];
    }

    public function array(): array
    {
        // Sample data untuk template - gunakan tahun ajaran yang valid
        return [
            ['1', 'X IPA 1', 'Kelas 10', 'IPA', '', '2025/2026', '32', 'Kelas Unggulan', 'Aktif'],
            ['2', 'X IPA 2', '10', 'IPA', '', '2025/2026', '32', '', 'Aktif'],
            ['', '', '', '', '', '', '', '', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Kelas*',
            'Tingkat*',
            'Jurusan',
            'Wali Kelas',
            'Tahun Ajaran*',
            'Kapasitas*',
            'Keterangan',
            'Status',
            '',
            'Keterangan'
        ];
    }

    public function title(): string
    {
        return 'Template Import Kelas';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 20,  // Nama Kelas
            'C' => 15,  // Tingkat
            'D' => 15,  // Jurusan
            'E' => 25,  // Wali Kelas
            'F' => 15,  // Tahun Ajaran
            'G' => 12,  // Kapasitas
            'H' => 30,  // Keterangan
            'I' => 12,  // Status
            'J' => 3,   // Separator
            'K' => 40,  // Keterangan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        // Base styles array
        $styles = [
            // Table header styling (will be row 4 after adding title rows)
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

            // Data rows styling (starting from row 5)
            "A1:I{$lastRow}" => [
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

            // Status column center alignment
            "I1:I{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Keterangan column styling
            "K1:K{$lastRow}" => [
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

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Insert title row at the top
                $sheet->insertNewRowBefore(1, 3);

                // Add title
                $sheet->setCellValue('A1', 'TEMPLATE IMPORT DATA KELAS');
                $sheet->mergeCells('A1:I1');
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

                // Add instructions
                $sheet->setCellValue('A2', 'Petunjuk: Isi data kelas mulai dari baris 5. Hapus contoh data sebelum upload.');
                $sheet->mergeCells('A2:I2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '666666']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ]
                ]);

                // Add empty row
                $sheet->setCellValue('A3', '');

                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(10);

                // Adjust header row (now row 4)
                $sheet->getRowDimension(4)->setRowHeight(25);

                // Add notes to the right side
                $notes = $this->getNotes();
                foreach ($notes as $index => $note) {
                    $row = $index + 4; // Start from row 4 (header row)
                    $sheet->setCellValue("K{$row}", $note);

                    // Style for note headers
                    if ($note === 'PANDUAN PENGISIAN:' || $note === 'Catatan:' || $note === 'Contoh Data:') {
                        $sheet->getStyle("K{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '2F4F4F']
                            ]
                        ]);
                    } else {
                        $sheet->getStyle("K{$row}")->applyFromArray([
                            'font' => [
                                'size' => 10,
                                'color' => ['rgb' => '666666']
                            ]
                        ]);
                    }
                }

                // Add data validation for specific columns
                $lastRow = 1000; // Set a reasonable limit for validation

                // Status validation
                $statusValidation = $sheet->getCell('I5')->getDataValidation();
                $statusValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $statusValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $statusValidation->setAllowBlank(false);
                $statusValidation->setShowInputMessage(true);
                $statusValidation->setShowErrorMessage(true);
                $statusValidation->setShowDropDown(true);
                $statusValidation->setFormula1('"Aktif,Tidak Aktif"');
                $statusValidation->setErrorTitle('Input Error');
                $statusValidation->setError('Pilih Aktif atau Tidak Aktif');
                $statusValidation->setPromptTitle('Pilih Status');
                $statusValidation->setPrompt('Status keaktifan kelas');

                // Copy status validation to all rows
                for ($row = 5; $row <= $lastRow; $row++) {
                    $sheet->getCell("I{$row}")->setDataValidation(clone $statusValidation);
                }

                // Set column K width to auto
                $sheet->getColumnDimension('K')->setAutoSize(true);
            }
        ];
    }
}
