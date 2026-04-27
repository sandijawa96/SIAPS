<?php

namespace App\Exports;

use App\Models\ManualAttendanceIncidentBatch;
use Maatwebsite\Excel\Concerns\FromArray;

class ManualAttendanceIncidentBatchExport implements FromArray
{
    public function __construct(
        private readonly ManualAttendanceIncidentBatch $batch,
        private readonly string $resultGroup = 'all',
        private readonly array $resultCodes = [],
    ) {
    }

    public function array(): array
    {
        $scopeLabel = (string) ($this->batch->preview_summary['scope_label'] ?? $this->batch->scope_type);
        $groupLabel = match (strtolower($this->resultGroup)) {
            'created' => 'Dibuat Saja',
            'skipped' => 'Dilewati Saja',
            'failed' => 'Gagal Saja',
            default => 'Semua Hasil',
        };
        $contextRows = [
            ['Laporan Hasil Batch Insiden Server'],
            ['Batch ID', $this->batch->id],
            ['Tanggal', optional($this->batch->tanggal)?->toDateString()],
            ['Scope', $scopeLabel],
            ['Filter Export', $groupLabel],
            ['Status Batch', $this->batch->status],
            ['Status Absensi Default', $this->batch->attendance_status],
            ['Alasan Insiden', $this->batch->reason],
            ['Total Scope', (int) $this->batch->total_scope_users],
            ['Dibuat', (int) $this->batch->created_count],
            ['Skip Existing', (int) $this->batch->skipped_existing_count],
            ['Skip Izin', (int) $this->batch->skipped_leave_count],
            ['Skip Non Wajib', (int) $this->batch->skipped_non_required_count],
            ['Skip Non Working', (int) $this->batch->skipped_non_working_count],
            ['Gagal', (int) $this->batch->failed_count],
            [],
        ];

        $headings = [[
            'User ID',
            'Nama Lengkap',
            'Email',
            'Tingkat',
            'Kelas',
            'Result Code',
            'Result Label',
            'Message',
            'Attendance ID',
            'Processed At',
        ]];

        $rows = $this->batch->items()
            ->when(!empty($this->resultCodes), function ($query) {
                $query->whereIn('result_code', $this->resultCodes);
            })
            ->orderBy('id')
            ->get([
                'user_id',
                'nama_lengkap',
                'email',
                'tingkat_label',
                'kelas_label',
                'result_code',
                'result_label',
                'message',
                'attendance_id',
                'processed_at',
            ])
            ->map(function ($item) {
                return [
                    $item->user_id,
                    $item->nama_lengkap,
                    $item->email,
                    $item->tingkat_label,
                    $item->kelas_label,
                    $item->result_code,
                    $item->result_label,
                    $item->message,
                    $item->attendance_id,
                    optional($item->processed_at)?->format('Y-m-d H:i:s'),
                ];
            })
            ->all();

        return array_merge($contextRows, $headings, $rows);
    }
}
