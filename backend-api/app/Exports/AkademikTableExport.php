<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AkademikTableExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents, WithColumnWidths, WithTitle
{
    private Collection $rows;

    /**
     * @var array<int, array{key:string,label:string,width?:int}>
     */
    private array $columns;

    /**
     * @var array<string, mixed>
     */
    private array $meta;

    private int $rowNumber = 0;
    /** @var array<int, string> */
    private array $columnKeys = [];

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @param array<int, array{key:string,label:string,width?:int}> $columns
     * @param array<string, mixed> $meta
     */
    public function __construct(Collection $rows, array $columns, array $meta = [])
    {
        $this->rows = $rows->values();
        $this->columns = $columns;
        $this->meta = $meta;
        $this->columnKeys = array_values(array_map(
            static fn (array $column): string => (string) ($column['key'] ?? ''),
            $columns
        ));
    }

    public function title(): string
    {
        return (string) ($this->meta['sheet_title'] ?? 'Data');
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values(array_map(
            fn (array $column): string => (string) ($column['label'] ?? $column['key']),
            $this->columns
        ));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        $preparedRow = is_array($row) ? $row : (array) $row;
        if (!array_key_exists('no', $preparedRow)) {
            $preparedRow['no'] = $this->rowNumber;
        }

        $result = [];
        foreach ($this->columns as $column) {
            $value = data_get($preparedRow, $column['key']);
            if (is_bool($value)) {
                $result[] = $value ? 'Ya' : 'Tidak';
            } elseif ($value === null || $value === '') {
                $result[] = '-';
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->columns as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $widths[$letter] = (int) ($column['width'] ?? 20);
        }

        return $widths;
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $totalColumns = max(1, count($this->columns));
                $lastColumn = Coordinate::stringFromColumnIndex($totalColumns);
                $highestRow = max(6, $sheet->getHighestRow());

                $title = (string) ($this->meta['title'] ?? 'Laporan Data');
                $subtitle = (string) ($this->meta['subtitle'] ?? '');
                $generatedBy = (string) ($this->meta['generated_by'] ?? '-');
                $generatedAt = (string) ($this->meta['generated_at'] ?? now()->format('Y-m-d H:i:s'));
                $filterSummary = (string) ($this->meta['filter_summary'] ?? 'Semua data');
                $disciplineLimitSummary = (string) ($this->meta['discipline_limit_summary'] ?? '-');

                $sheet->setCellValue('A1', $title);
                $sheet->setCellValue('A2', $subtitle);
                $sheet->setCellValue('A3', 'Batas Disiplin Siswa: ' . $disciplineLimitSummary);
                $sheet->setCellValue('A4', 'Generated: ' . $generatedAt . ' | By: ' . $generatedBy);
                $sheet->setCellValue('A5', 'Filter: ' . $filterSummary);

                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->mergeCells("A4:{$lastColumn}4");
                $sheet->mergeCells("A5:{$lastColumn}5");

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 15,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['argb' => 'FF1D4ED8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A3:{$lastColumn}5")->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['argb' => 'FF374151'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle("A6:{$lastColumn}6")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1D4ED8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF1E3A8A'],
                        ],
                    ],
                ]);

                if ($highestRow >= 7) {
                    $sheet->getStyle("A7:{$lastColumn}{$highestRow}")->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_TOP,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFD1D5DB'],
                            ],
                        ],
                    ]);
                }

                $centerKeys = [
                    'no',
                    'tanggal',
                    'hadir',
                    'terlambat',
                    'tap',
                    'izin',
                    'sakit',
                    'alpha',
                    'persentase_kehadiran',
                    'pelanggaran',
                    'status_batas',
                ];

                foreach ($this->columnKeys as $index => $key) {
                    if ($highestRow < 7) {
                        break;
                    }

                    if (!in_array($key, $centerKeys, true)) {
                        continue;
                    }

                    $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $range = "{$columnLetter}7:{$columnLetter}{$highestRow}";
                    $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach ($this->columnKeys as $index => $key) {
                    if ($highestRow < 7) {
                        break;
                    }

                    $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $range = "{$columnLetter}7:{$columnLetter}{$highestRow}";

                    if ($key === 'persentase_kehadiran') {
                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    }
                }

                $sheet->setAutoFilter("A6:{$lastColumn}6");
                $sheet->freezePane('A7');
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(6)->setRowHeight(24);
            },
        ];
    }
}
