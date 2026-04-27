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

class MapelTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes(): array
    {
        return [
            'PANDUAN PENGISIAN:',
            '- Kode Mapel wajib unik (contoh: MTK10).',
            '- Nama Mapel wajib diisi.',
            '- Kelompok opsional (contoh: Wajib/Peminatan/Muatan Lokal).',
            '- Tingkat bisa diisi ID, kode, atau nama tingkat.',
            '- Status: Aktif atau Tidak Aktif.',
            '- Keterangan opsional.',
            '- Hapus contoh data sebelum upload.',
            '',
            'Catatan:',
            '- Gunakan template ini agar header terbaca sistem.',
            '- Template dibuat: ' . now()->format('d/m/Y H:i'),
            '- Versi template: 1.1',
        ];
    }

    public function headings(): array
    {
        return [
            'Kode Mapel*',
            'Nama Mapel*',
            'Kelompok',
            'Tingkat (ID/Kode/Nama)',
            'Status (Aktif/Tidak Aktif)',
            'Keterangan',
            '',
            'Panduan',
        ];
    }

    public function array(): array
    {
        return [
            ['MTK10', 'Matematika', 'Wajib', 'Kelas 10', 'Aktif', 'Contoh mapel wajib'],
            ['BIO11', 'Biologi', 'Peminatan', '11', 'Aktif', 'Contoh mapel peminatan'],
            ['', '', '', '', '', ''],
        ];
    }

    public function title(): string
    {
        return 'Template Import Mapel';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 30,
            'C' => 18,
            'D' => 22,
            'E' => 20,
            'F' => 28,
            'G' => 3,
            'H' => 44,
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

                $sheet->setCellValue('A1', 'TEMPLATE IMPORT MASTER MATA PELAJARAN');
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

                $sheet->setCellValue('A2', 'Petunjuk: Isi data mapel mulai baris 5. Hapus contoh data sebelum upload.');
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

                $kelompokValidation = $sheet->getCell('C5')->getDataValidation();
                $kelompokValidation->setType(DataValidation::TYPE_LIST);
                $kelompokValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $kelompokValidation->setAllowBlank(true);
                $kelompokValidation->setShowInputMessage(true);
                $kelompokValidation->setShowErrorMessage(true);
                $kelompokValidation->setShowDropDown(true);
                $kelompokValidation->setFormula1('"Wajib,Peminatan,Muatan Lokal"');
                $kelompokValidation->setPromptTitle('Kelompok Mapel');
                $kelompokValidation->setPrompt('Pilih kelompok mapel yang sesuai');
                $kelompokValidation->setErrorTitle('Input Error');
                $kelompokValidation->setError('Gunakan pilihan pada dropdown');

                $statusValidation = $sheet->getCell('E5')->getDataValidation();
                $statusValidation->setType(DataValidation::TYPE_LIST);
                $statusValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $statusValidation->setAllowBlank(true);
                $statusValidation->setShowInputMessage(true);
                $statusValidation->setShowErrorMessage(true);
                $statusValidation->setShowDropDown(true);
                $statusValidation->setFormula1('"Aktif,Tidak Aktif"');
                $statusValidation->setPromptTitle('Status Mapel');
                $statusValidation->setPrompt('Pilih Aktif atau Tidak Aktif');
                $statusValidation->setErrorTitle('Input Error');
                $statusValidation->setError('Status harus Aktif atau Tidak Aktif');

                for ($row = 5; $row <= $lastValidationRow; $row++) {
                    $sheet->getCell("C{$row}")->setDataValidation(clone $kelompokValidation);
                    $sheet->getCell("E{$row}")->setDataValidation(clone $statusValidation);
                }

                $sheet->getColumnDimension('H')->setAutoSize(true);
            },
        ];
    }
}
