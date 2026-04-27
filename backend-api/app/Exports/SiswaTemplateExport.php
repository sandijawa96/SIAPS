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

class SiswaTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes()
    {
        return [
            'PANDUAN PENGISIAN:',
            '• Username: Otomatis dibuat dari NIS (tidak perlu diisi)',
            '• Password: Otomatis dibuat dari tanggal lahir format DDMMYYYY',
            '• NIS: Nomor Induk Siswa, harus unik, wajib diisi',
            '• NISN: Nomor Induk Siswa Nasional, harus unik, wajib diisi',
            '• Email: Format email valid, opsional',
            '• Tanggal Lahir: Format YYYY-MM-DD (contoh: 2005-01-15)',
            '• Jenis Kelamin: Hanya boleh diisi L atau P',
            '• Kelas: Sesuai dengan kelas yang tersedia di sistem',
            '• Tahun Ajaran: Sesuai dengan tahun ajaran aktif',
            '• Tanggal Masuk: Format YYYY-MM-DD, harus dalam rentang tahun ajaran',
            '• Status: Hanya boleh diisi Aktif atau Tidak Aktif',
            '• Hapus contoh data sebelum upload',
            '',
            'Contoh Data:',
            '• NIS: 2024001, Nama: Ahmad Rizki, Email: ahmad@email.com',
            '• Tanggal Lahir: 2005-03-15 → Password: 15032005',
            '• Jenis Kelamin: L, Kelas: X-1, Tanggal Masuk: 2024-07-15',
            '',
            'Catatan:',
            '• Template dibuat: ' . date('d/m/Y H:i'),
            '• Versi template: 2.1',
        ];
    }

    public function array(): array
    {
        // Sample data untuk template
        return [
            ['1', '2024001', 'Ahmad Rizki', 'ahmad@email.com', '1234567890', '2005-03-15', 'L', 'X-1', '2024/2025', '2024-07-15', '081234567890', 'Aktif'],
            ['2', '2024002', 'Siti Aminah', 'siti@email.com', '0987654321', '2005-04-20', 'P', 'X-2', '2024/2025', '2024-07-16', '082345678901', 'Aktif'],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'No',
            'NIS*',
            'Nama Lengkap*',
            'Email',
            'NISN*',
            'Tanggal Lahir*',
            'Jenis Kelamin*',
            'Kelas*',
            'Tahun Ajaran*',
            'Tanggal Masuk*',
            'No. Telepon Orang Tua*',
            'Status',
            '',
            'Keterangan'
        ];
    }

    public function title(): string
    {
        return 'Template Import Siswa';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 15,  // NIS
            'C' => 30,  // Nama Lengkap
            'D' => 30,  // Email
            'E' => 15,  // NISN
            'F' => 15,  // Tanggal Lahir
            'G' => 15,  // Jenis Kelamin
            'H' => 20,  // Kelas
            'I' => 20,  // Tahun Ajaran
            'J' => 15,  // Tanggal Masuk
            'K' => 20,  // No. Telepon Orang Tua
            'L' => 12,  // Status
            'M' => 3,   // Separator
            'N' => 40,  // Keterangan
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
            "A1:L{$lastRow}" => [
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
            "L1:L{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Keterangan column styling
            "N1:N{$lastRow}" => [
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
                $sheet->setCellValue('A1', 'TEMPLATE IMPORT DATA SISWA');
                $sheet->mergeCells('A1:L1');
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
                $sheet->setCellValue('A2', 'Petunjuk: Isi data siswa mulai dari baris 5. Hapus contoh data sebelum upload.');
                $sheet->mergeCells('A2:L2');
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
                    $sheet->setCellValue("N{$row}", $note);

                    // Style for note headers
                    if ($note === 'PANDUAN PENGISIAN:' || $note === 'Catatan:' || $note === 'Contoh Data:') {
                        $sheet->getStyle("N{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '2F4F4F']
                            ]
                        ]);
                    } else {
                        $sheet->getStyle("N{$row}")->applyFromArray([
                            'font' => [
                                'size' => 10,
                                'color' => ['rgb' => '666666']
                            ]
                        ]);
                    }
                }

                // Add data validation for specific columns
                $lastRow = 1000; // Set a reasonable limit for validation

                // Jenis Kelamin validation
                $validation = $sheet->getCell('G5')->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"L,P"');
                $validation->setErrorTitle('Input Error');
                $validation->setError('Pilih L atau P');
                $validation->setPromptTitle('Pilih Jenis Kelamin');
                $validation->setPrompt('L = Laki-laki, P = Perempuan');

                // Copy validation to all rows
                for ($row = 5; $row <= $lastRow; $row++) {
                    $sheet->getCell("G{$row}")->setDataValidation(clone $validation);
                }

                // Status validation
                $statusValidation = $sheet->getCell('L5')->getDataValidation();
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
                $statusValidation->setPrompt('Status keaktifan siswa');

                // Copy status validation to all rows
                for ($row = 5; $row <= $lastRow; $row++) {
                    $sheet->getCell("L{$row}")->setDataValidation(clone $statusValidation);
                }

                // Set column N width to auto
                $sheet->getColumnDimension('N')->setAutoSize(true);
            }
        ];
    }
}
