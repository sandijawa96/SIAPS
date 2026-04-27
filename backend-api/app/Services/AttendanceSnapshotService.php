<?php

namespace App\Services;

use App\Models\AttendanceSchema;
use App\Models\TahunAjaran;
use App\Models\User;

class AttendanceSnapshotService
{
    public function __construct(
        private readonly AttendanceSchemaService $attendanceSchemaService,
        private readonly AttendanceTimeService $attendanceTimeService,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{schema:?AttendanceSchema,attendance_setting_id:?int,working_hours:array<string,mixed>,settings_snapshot:array<string,mixed>}
     */
    public function captureForUser(User $user, array $options = []): array
    {
        $effectiveSchema = ($options['schema'] ?? null) instanceof AttendanceSchema
            ? $options['schema']
            : $this->attendanceSchemaService->getEffectiveSchema($user);
        $workingHours = is_array($options['working_hours'] ?? null)
            ? $options['working_hours']
            : $this->attendanceTimeService->getWorkingHours($user);

        $activeClass = $this->resolveActiveClass($user);
        $tahunAjaran = $activeClass?->tahunAjaran ?: TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();

        $settingsSnapshot = [
            'schema' => [
                'id' => $effectiveSchema?->id ?? ($workingHours['schema_id'] ?? null),
                'name' => $effectiveSchema?->schema_name ?? ($workingHours['schema_name'] ?? null),
                'type' => $effectiveSchema?->schema_type,
                'version' => $effectiveSchema?->version,
            ],
            'working_hours' => [
                'jam_masuk' => $workingHours['jam_masuk'] ?? null,
                'jam_pulang' => $workingHours['jam_pulang'] ?? null,
                'toleransi' => $workingHours['toleransi'] ?? null,
                'minimal_open_time' => $workingHours['minimal_open_time'] ?? null,
                'wajib_gps' => $workingHours['wajib_gps'] ?? null,
                'wajib_foto' => $workingHours['wajib_foto'] ?? null,
                'hari_kerja' => $workingHours['hari_kerja'] ?? null,
                'source' => $workingHours['source'] ?? null,
            ],
            'attendance_window' => $options['attendance_window'] ?? null,
            'class_context' => [
                'kelas_id' => $activeClass?->id,
                'kelas_name' => $activeClass?->nama_kelas,
                'tahun_ajaran_id' => $activeClass?->tahun_ajaran_id ?? $tahunAjaran?->id,
                'tahun_ajaran_name' => $activeClass?->tahunAjaran?->nama ?? $tahunAjaran?->nama,
            ],
            'captured_at' => now()->toDateTimeString(),
        ];

        return [
            'schema' => $effectiveSchema,
            'attendance_setting_id' => $effectiveSchema?->id
                ? (int) $effectiveSchema->id
                : (isset($workingHours['schema_id']) && is_numeric($workingHours['schema_id'])
                    ? (int) $workingHours['schema_id']
                    : null),
            'working_hours' => $workingHours,
            'settings_snapshot' => $settingsSnapshot,
        ];
    }

    private function resolveActiveClass(User $user): mixed
    {
        return $user->kelas()
            ->with('tahunAjaran')
            ->wherePivot('is_active', true)
            ->orderByDesc('kelas_siswa.updated_at')
            ->first()
            ?? $user->kelas()->with('tahunAjaran')->orderByDesc('kelas_siswa.updated_at')->first();
    }
}
