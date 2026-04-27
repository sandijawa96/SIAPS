<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ActivityLogExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $columns,
        private readonly array $meta = []
    ) {
    }

    public function collection(): Collection
    {
        $keys = array_column($this->columns, 'key');

        return $this->rows->map(function (array $row) use ($keys) {
            return collect($keys)->map(fn (string $key) => $row[$key] ?? null)->all();
        });
    }

    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }
}
