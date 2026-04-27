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

class GuruMapelTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes(): array
    {
        return [
            'PANDUAN PENGISIAN:',
            '- Kolom guru bisa diisi NIP, email, username, atau nama lengkap.',
            '- Mapel bisa diisi kode mapel atau nama mapel.',
            '- Kelas wajib sesuai master kelas (contoh: X-1).',
            '- Tahun ajaran wajib sesuai nama di sistem (contoh: 2025/2026).',
            '- Jam per minggu opsional (angka 0-60).',
            '- Status: aktif atau tidak_aktif.',
            '- Hapus contoh data sebelum upload.',
            '',
            'Catatan:',
            '- Kombinasi guru + mapel + kelas + tahun ajaran harus unik.',
            '- Gunakan mode import sesuai kebutuhan (auto/create/update).',
            '- Template dibuat: ' . now()->format('d/m/Y H:i'),
            '- Versi template: 1.1',
        ];
    }

    public function headings(): array
    {
        return [
            'Guru (NIP/Email/Username)*',
            'Mata Pelajaran (Kode/Nama)*',
            'Kelas*',
            'Tahun Ajaran*',
            'Jam Per Minggu',
            'Status (aktif/tidak_aktif)',
            '',
            'Panduan',
        ];
    }

    public function array(): array
    {
        return [
            ['198701012022121001', 'MTK10', 'X-1', '2025/2026', '4', 'aktif'],
            ['guru.bahasa@sman1sumbercirebon.sch.id', 'Bahasa Indonesia', 'XI-2', '2025/2026', '3', 'aktif'],
            ['', '', '', '', '', ''],
        ];
    }

    public function title(): string
    {
        return 'Template Import Guru Mapel';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 30,
            'C' => 16,
            'D' => 16,
            'E' => 16,
            'F' => 24,
            'G' => 3,
            'H' => 48,
        ];
    }

    public function styles(Worksheet $sheet): array
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
                    'startColor' => ['rgb' => '4472C4'],
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
            "A1:F{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            "E1:E{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "F1:F{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "H1:H{$lastRow}" => [
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => '666666'],
                ],
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => Alignment::VERTICAL_TOP,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'TEMPLATE IMPORT PENUGASAN GURU MAPEL');
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '2F4F4F'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A2', 'Petunjuk: Isi data penugasan mulai baris 5. Hapus contoh data sebelum upload.');
                $sheet->mergeCells('A2:F2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '666666'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ],
                ]);

                $sheet->setCellValue('A3', '');

                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(10);
                $sheet->getRowDimension(4)->setRowHeight(25);

                $sheet->freezePane('A5');
                $sheet->setAutoFilter('A4:F4');

                $notes = $this->getNotes();
                foreach ($notes as $index => $note) {
                    $row = $index + 4;
                    $sheet->setCellValue("H{$row}", $note);

                    if (in_array($note, ['PANDUAN PENGISIAN:', 'Catatan:'], true)) {
                        $sheet->getStyle("H{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '2F4F4F'],
                            ],
                        ]);
                    } else {
                        $sheet->getStyle("H{$row}")->applyFromArray([
                            'font' => [
                                'size' => 10,
                                'color' => ['rgb' => '666666'],
                            ],
                        ]);
                    }
                }

                $lastValidationRow = 1000;

                $statusValidation = $sheet->getCell('F5')->getDataValidation();
                $statusValidation->setType(DataValidation::TYPE_LIST);
                $statusValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $statusValidation->setAllowBlank(true);
                $statusValidation->setShowInputMessage(true);
                $statusValidation->setShowErrorMessage(true);
                $statusValidation->setShowDropDown(true);
                $statusValidation->setFormula1('"aktif,tidak_aktif"');
                $statusValidation->setPromptTitle('Status Penugasan');
                $statusValidation->setPrompt('Pilih aktif atau tidak_aktif');
                $statusValidation->setErrorTitle('Input Error');
                $statusValidation->setError('Status harus aktif atau tidak_aktif');

                $jpValidation = $sheet->getCell('E5')->getDataValidation();
                $jpValidation->setType(DataValidation::TYPE_WHOLE);
                $jpValidation->setOperator(DataValidation::OPERATOR_BETWEEN);
                $jpValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $jpValidation->setAllowBlank(true);
                $jpValidation->setShowInputMessage(true);
                $jpValidation->setShowErrorMessage(true);
                $jpValidation->setFormula1('0');
                $jpValidation->setFormula2('60');
                $jpValidation->setErrorTitle('Input Error');
                $jpValidation->setError('Jam per minggu harus angka 0-60');
                $jpValidation->setPromptTitle('Jam Per Minggu');
                $jpValidation->setPrompt('Isi angka 0 sampai 60');

                for ($row = 5; $row <= $lastValidationRow; $row++) {
                    $sheet->getCell("E{$row}")->setDataValidation(clone $jpValidation);
                    $sheet->getCell("F{$row}")->setDataValidation(clone $statusValidation);
                }

                $sheet->getColumnDimension('H')->setAutoSize(true);
            },
        ];
    }
}
