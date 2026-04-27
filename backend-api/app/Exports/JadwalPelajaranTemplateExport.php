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

class JadwalPelajaranTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    private function getNotes(): array
    {
        return [
            'PANDUAN PENGISIAN:',
            '- Isi guru dengan NIP, email, username, atau nama lengkap.',
            '- Isi mapel dengan kode mapel atau nama mapel.',
            '- Hari wajib: senin, selasa, rabu, kamis, jumat, sabtu, minggu.',
            '- Jam Ke wajib angka >= 1 dan mengikuti pengaturan jadwal.',
            '- Jumlah JP Berurutan opsional (default 1).',
            '- Semester: ganjil, genap, atau full.',
            '- Status: draft, published, atau archived.',
            '- Aktif: ya atau tidak.',
            '- Isi kolom ID jika ingin update jadwal tertentu.',
            '- Hapus contoh data sebelum upload.',
            '',
            'Catatan:',
            '- Pastikan penugasan guru-mapel sudah tersedia.',
            '- Template dibuat: ' . now()->format('d/m/Y H:i'),
            '- Versi template: 1.1',
        ];
    }

    public function headings(): array
    {
        return [
            'ID (Opsional untuk Update)',
            'Guru (NIP/Email/Username)*',
            'Mata Pelajaran (Kode/Nama)*',
            'Kelas*',
            'Tahun Ajaran*',
            'Semester (ganjil/genap/full)',
            'Hari*',
            'Jam Ke (JP)*',
            'Jumlah JP Berurutan',
            'Ruangan',
            'Status (draft/published/archived)',
            'Aktif (ya/tidak)',
            '',
            'Panduan',
        ];
    }

    public function array(): array
    {
        return [
            ['', '198701012022121001', 'MTK10', 'X-1', '2025/2026', 'ganjil', 'senin', '1', '2', 'R-101', 'draft', 'ya'],
            ['', 'guru.fisika@sman1sumbercirebon.sch.id', 'Fisika', 'XI-1', '2025/2026', 'full', 'rabu', '3', '1', 'Lab Fisika', 'published', 'ya'],
            ['', '', '', '', '', '', '', '', '', '', '', ''],
        ];
    }

    public function title(): string
    {
        return 'Template Import Jadwal';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 28,
            'C' => 30,
            'D' => 14,
            'E' => 16,
            'F' => 24,
            'G' => 12,
            'H' => 14,
            'I' => 12,
            'J' => 18,
            'K' => 28,
            'L' => 14,
            'M' => 3,
            'N' => 56,
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
            "A1:L{$lastRow}" => [
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
            "H1:I{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            "N1:N{$lastRow}" => [
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

                $sheet->setCellValue('A1', 'TEMPLATE IMPORT JADWAL PELAJARAN');
                $sheet->mergeCells('A1:L1');
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

                $sheet->setCellValue('A2', 'Petunjuk: Isi data jadwal mulai baris 5. Hapus contoh data sebelum upload.');
                $sheet->mergeCells('A2:L2');
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
                $sheet->setAutoFilter('A4:L4');

                $notes = $this->getNotes();
                foreach ($notes as $index => $note) {
                    $row = $index + 4;
                    $sheet->setCellValue("N{$row}", $note);

                    if (in_array($note, ['PANDUAN PENGISIAN:', 'Catatan:'], true)) {
                        $sheet->getStyle("N{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => ['rgb' => '2F4F4F'],
                            ],
                        ]);
                    } else {
                        $sheet->getStyle("N{$row}")->applyFromArray([
                            'font' => [
                                'size' => 10,
                                'color' => ['rgb' => '666666'],
                            ],
                        ]);
                    }
                }

                $lastValidationRow = 1000;

                $semesterValidation = $sheet->getCell('F5')->getDataValidation();
                $semesterValidation->setType(DataValidation::TYPE_LIST);
                $semesterValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $semesterValidation->setAllowBlank(true);
                $semesterValidation->setShowInputMessage(true);
                $semesterValidation->setShowErrorMessage(true);
                $semesterValidation->setShowDropDown(true);
                $semesterValidation->setFormula1('"ganjil,genap,full"');
                $semesterValidation->setPromptTitle('Semester');
                $semesterValidation->setPrompt('Pilih semester jadwal');
                $semesterValidation->setErrorTitle('Input Error');
                $semesterValidation->setError('Semester harus ganjil, genap, atau full');

                $hariValidation = $sheet->getCell('G5')->getDataValidation();
                $hariValidation->setType(DataValidation::TYPE_LIST);
                $hariValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $hariValidation->setAllowBlank(false);
                $hariValidation->setShowInputMessage(true);
                $hariValidation->setShowErrorMessage(true);
                $hariValidation->setShowDropDown(true);
                $hariValidation->setFormula1('"senin,selasa,rabu,kamis,jumat,sabtu,minggu"');
                $hariValidation->setPromptTitle('Hari');
                $hariValidation->setPrompt('Pilih hari jadwal');
                $hariValidation->setErrorTitle('Input Error');
                $hariValidation->setError('Hari harus sesuai opsi dropdown');

                $statusValidation = $sheet->getCell('K5')->getDataValidation();
                $statusValidation->setType(DataValidation::TYPE_LIST);
                $statusValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $statusValidation->setAllowBlank(true);
                $statusValidation->setShowInputMessage(true);
                $statusValidation->setShowErrorMessage(true);
                $statusValidation->setShowDropDown(true);
                $statusValidation->setFormula1('"draft,published,archived"');
                $statusValidation->setPromptTitle('Status Jadwal');
                $statusValidation->setPrompt('Pilih status jadwal');
                $statusValidation->setErrorTitle('Input Error');
                $statusValidation->setError('Status harus draft, published, atau archived');

                $isActiveValidation = $sheet->getCell('L5')->getDataValidation();
                $isActiveValidation->setType(DataValidation::TYPE_LIST);
                $isActiveValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $isActiveValidation->setAllowBlank(true);
                $isActiveValidation->setShowInputMessage(true);
                $isActiveValidation->setShowErrorMessage(true);
                $isActiveValidation->setShowDropDown(true);
                $isActiveValidation->setFormula1('"ya,tidak"');
                $isActiveValidation->setPromptTitle('Aktif');
                $isActiveValidation->setPrompt('Pilih ya atau tidak');
                $isActiveValidation->setErrorTitle('Input Error');
                $isActiveValidation->setError('Nilai aktif harus ya atau tidak');

                $jamKeValidation = $sheet->getCell('H5')->getDataValidation();
                $jamKeValidation->setType(DataValidation::TYPE_WHOLE);
                $jamKeValidation->setOperator(DataValidation::OPERATOR_BETWEEN);
                $jamKeValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $jamKeValidation->setAllowBlank(false);
                $jamKeValidation->setShowInputMessage(true);
                $jamKeValidation->setShowErrorMessage(true);
                $jamKeValidation->setFormula1('1');
                $jamKeValidation->setFormula2('16');
                $jamKeValidation->setPromptTitle('Jam Ke');
                $jamKeValidation->setPrompt('Isi angka JP dari 1 sampai 16');
                $jamKeValidation->setErrorTitle('Input Error');
                $jamKeValidation->setError('Jam ke harus angka 1-16');

                $jpCountValidation = $sheet->getCell('I5')->getDataValidation();
                $jpCountValidation->setType(DataValidation::TYPE_WHOLE);
                $jpCountValidation->setOperator(DataValidation::OPERATOR_BETWEEN);
                $jpCountValidation->setErrorStyle(DataValidation::STYLE_STOP);
                $jpCountValidation->setAllowBlank(true);
                $jpCountValidation->setShowInputMessage(true);
                $jpCountValidation->setShowErrorMessage(true);
                $jpCountValidation->setFormula1('1');
                $jpCountValidation->setFormula2('12');
                $jpCountValidation->setPromptTitle('Jumlah JP');
                $jpCountValidation->setPrompt('Isi angka 1 sampai 12');
                $jpCountValidation->setErrorTitle('Input Error');
                $jpCountValidation->setError('Jumlah JP harus angka 1-12');

                for ($row = 5; $row <= $lastValidationRow; $row++) {
                    $sheet->getCell("F{$row}")->setDataValidation(clone $semesterValidation);
                    $sheet->getCell("G{$row}")->setDataValidation(clone $hariValidation);
                    $sheet->getCell("H{$row}")->setDataValidation(clone $jamKeValidation);
                    $sheet->getCell("I{$row}")->setDataValidation(clone $jpCountValidation);
                    $sheet->getCell("K{$row}")->setDataValidation(clone $statusValidation);
                    $sheet->getCell("L{$row}")->setDataValidation(clone $isActiveValidation);
                }

                $sheet->getColumnDimension('N')->setAutoSize(true);
            },
        ];
    }
}
