<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KelasNaikKelasTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [
            ['1', '20240001', 'Andi Pratama', 'X IPA 1', 'Siswa Baru'],
            ['2', '20230012', 'Siti Aulia', 'XI IPS 2', 'Naik Kelas'],
            ['', '', '', '', ''],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No',
            'NIS*',
            'Nama (Penanda)',
            'Kelas*',
            'Keterangan*',
        ];
    }

    public function title(): string
    {
        return 'Template Siswa Baru Naik Kelas';
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 28,
            'D' => 24,
            'E' => 20,
            'F' => 3,
            'G' => 54,
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1D4ED8'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
            "A1:E{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            "G1:G{$lastRow}" => [
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => '4B5563'],
                ],
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'TEMPLATE IMPORT SISWA BARU / NAIK KELAS');
                $sheet->mergeCells('A1:E1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 15,
                        'color' => ['rgb' => '1F2937'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A2', "Isi data mulai baris 5. Kolom wajib: NIS, Kelas, Keterangan. Keterangan: 'Siswa Baru' atau 'Naik Kelas'.");
                $sheet->mergeCells('A2:E2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '4B5563'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A3', '');

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(10);
                $sheet->getRowDimension(4)->setRowHeight(24);

                $validation = $sheet->getCell('E5')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Input tidak valid');
                $validation->setError('Pilih Keterangan: Siswa Baru atau Naik Kelas.');
                $validation->setPromptTitle('Pilih Mode');
                $validation->setPrompt('Gunakan dropdown: Siswa Baru atau Naik Kelas.');
                $validation->setFormula1('"Siswa Baru,Naik Kelas"');

                for ($row = 5; $row <= 1000; $row++) {
                    $sheet->getCell("E{$row}")->setDataValidation(clone $validation);
                }

                $notes = [
                    'PANDUAN',
                    "- Isi NIS siswa yang sudah terdaftar sebagai user siswa.",
                    '- Kolom Nama hanya untuk penanda agar tidak salah siswa.',
                    "- Isi Kelas sesuai nama kelas tujuan di sistem.",
                    "- Keterangan = Siswa Baru: hanya untuk siswa tanpa kelas aktif.",
                    "- Keterangan = Naik Kelas: untuk siswa yang sudah punya kelas aktif.",
                    "- Naik Kelas akan ditolak jika kelas tujuan setingkat / lebih rendah.",
                    "- Naik Kelas akan ditolak jika tahun ajaran tujuan tidak lebih tinggi.",
                ];

                foreach ($notes as $index => $note) {
                    $row = $index + 4;
                    $sheet->setCellValue("G{$row}", $note);
                    if ($index === 0) {
                        $sheet->getStyle("G{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '1F2937'],
                            ],
                        ]);
                    }
                }
            },
        ];
    }
}
