<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class LiveTrackingExport implements FromArray
{
    /**
     * @param array<int, string> $headings
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<int, mixed>> $contextRows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $contextRows = []
    ) {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $mappedRows = array_map(
            static fn (array $row): array => array_values($row),
            $this->rows
        );

        return array_merge($this->contextRows, [$this->headings], $mappedRows);
    }
}
