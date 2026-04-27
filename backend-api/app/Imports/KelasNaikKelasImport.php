<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\SiswaTransisi;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;

class KelasNaikKelasImport implements ToCollection
{
    private const MODE_NEW_STUDENT = 'siswa_baru';
    private const MODE_PROMOTION = 'naik_kelas';
    private const MIN_VALID_TRANSITION_DATE = '2000-01-01';

    /**
     * @var array<int, string>
     */
    protected array $errors = [];

    protected int $promoted = 0;

    protected int $assignedNew = 0;

    protected int $skipped = 0;

    protected int $processedBy;

    protected ?int $targetTahunAjaranId;

    protected string $tanggalTransisi;

    public function __construct(int $processedBy, ?int $targetTahunAjaranId = null, ?string $tanggalTransisi = null)
    {
        $this->processedBy = $processedBy;
        $this->targetTahunAjaranId = $targetTahunAjaranId;
        $this->tanggalTransisi = $this->resolveTransitionDate($tanggalTransisi);
    }

    private function resolveTransitionDate(?string $tanggalTransisi): string
    {
        $fallback = now()->toDateString();

        $raw = trim((string) $tanggalTransisi);
        if ($raw === '' || $raw === '0') {
            return $fallback;
        }

        try {
            $parsed = Carbon::parse($raw)->toDateString();
            if ($parsed < self::MIN_VALID_TRANSITION_DATE) {
                return $fallback;
            }

            return $parsed;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;

                // Skip row title/instruction/header from template.
                if ($index < 4) {
                    continue;
                }

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                try {
                    $rowData = $row instanceof Collection ? $row->toArray() : (array) $row;
                    $validated = $this->validateRow($rowData, $rowNumber);
                    if (!$validated) {
                        $this->skipped++;
                        continue;
                    }

                    $this->processPromotion($validated);
                    if (($validated['mode'] ?? self::MODE_PROMOTION) === self::MODE_NEW_STUDENT) {
                        $this->assignedNew++;
                    } else {
                        $this->promoted++;
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->skipped++;
                    Log::warning('Import siswa baru/naik kelas gagal di satu baris', [
                        'row' => $rowNumber,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new \Exception('Import siswa baru/naik kelas gagal: ' . $e->getMessage(), 0, $e);
        }
    }

    private function isEmptyRow(mixed $row): bool
    {
        $data = $row instanceof Collection ? $row->toArray() : (array) $row;
        return empty(array_filter($data, static function ($value) {
            return trim((string) $value) !== '';
        }));
    }

    /**
     * @param array<int, mixed> $row
     * @return array<string, mixed>|false
     */
    private function validateRow(array $row, int $rowNumber): array|false
    {
        $data = [
            'nis' => trim((string) ($row[1] ?? '')),
            'nama' => trim((string) ($row[2] ?? '')),
            'kelas_tujuan' => trim((string) ($row[3] ?? '')),
            'keterangan' => trim((string) ($row[4] ?? '')),
        ];

        $validator = Validator::make($data, [
            'nis' => 'required|string|max:30',
            'nama' => 'nullable|string|max:255',
            'kelas_tujuan' => 'required|string|max:100',
            'keterangan' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            $this->errors[] = "Baris {$rowNumber}: " . implode(', ', $validator->errors()->all());
            return false;
        }

        $mode = $this->normalizeMode($data['keterangan']);
        if ($mode === null) {
            $this->errors[] = "Baris {$rowNumber}: Keterangan harus 'Siswa Baru' atau 'Naik Kelas'";
            return false;
        }

        $siswa = User::query()
            ->where('nis', $data['nis'])
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->first();

        if (!$siswa) {
            $this->errors[] = "Baris {$rowNumber}: Siswa dengan NIS '{$data['nis']}' tidak ditemukan";
            return false;
        }

        $kelasAktif = DB::table('kelas_siswa')
            ->where('siswa_id', $siswa->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $targetQuery = Kelas::query()
            ->with(['tingkat', 'tahunAjaran'])
            ->whereRaw('LOWER(TRIM(nama_kelas)) = ?', [strtolower($data['kelas_tujuan'])])
            ->whereHas('tahunAjaran', function ($query) {
                $query->canManageClasses();
            });

        if ($this->targetTahunAjaranId !== null) {
            $targetQuery->where('tahun_ajaran_id', $this->targetTahunAjaranId);
        }

        $kelasTujuanList = $targetQuery->get();
        if ($kelasTujuanList->isEmpty()) {
            $this->errors[] = "Baris {$rowNumber}: Kelas tujuan '{$data['kelas_tujuan']}' tidak ditemukan";
            return false;
        }

        if ($kelasTujuanList->count() > 1) {
            $tahunAjaranNames = $kelasTujuanList
                ->map(fn (Kelas $kelas) => (string) optional($kelas->tahunAjaran)->nama)
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');
            $this->errors[] = "Baris {$rowNumber}: Kelas tujuan '{$data['kelas_tujuan']}' ambigu. Tersedia pada tahun ajaran: {$tahunAjaranNames}";
            return false;
        }

        /** @var Kelas $kelasTujuan */
        $kelasTujuan = $kelasTujuanList->first();

        if (!$kelasTujuan->tingkat || !$kelasTujuan->tahunAjaran) {
            $this->errors[] = "Baris {$rowNumber}: Data kelas tujuan '{$data['kelas_tujuan']}' tidak lengkap";
            return false;
        }

        if ($mode === self::MODE_PROMOTION) {
            if (!$kelasAktif) {
                $this->errors[] = "Baris {$rowNumber}: Mode Naik Kelas butuh siswa yang sudah memiliki kelas aktif";
                return false;
            }

            $kelasAsal = Kelas::with(['tingkat', 'tahunAjaran'])->find((int) $kelasAktif->kelas_id);
            if (!$kelasAsal || !$kelasAsal->tingkat || !$kelasAsal->tahunAjaran) {
                $this->errors[] = "Baris {$rowNumber}: Data kelas asal siswa NIS '{$data['nis']}' tidak lengkap";
                return false;
            }

            if ((int) $kelasTujuan->id === (int) $kelasAsal->id) {
                $this->errors[] = "Baris {$rowNumber}: Kelas tujuan tidak boleh sama dengan kelas aktif saat ini";
                return false;
            }

            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                $this->errors[] = "Baris {$rowNumber}: Urutan tingkat kelas asal/tujuan belum dikonfigurasi";
                return false;
            }

            if ((int) $kelasTujuan->tingkat->urutan <= (int) $kelasAsal->tingkat->urutan) {
                $this->errors[] = "Baris {$rowNumber}: Kelas tujuan harus lebih tinggi dari kelas asal";
                return false;
            }

            $mulaiAsal = Carbon::parse((string) $kelasAsal->tahunAjaran->tanggal_mulai);
            $mulaiTujuan = Carbon::parse((string) $kelasTujuan->tahunAjaran->tanggal_mulai);
            if ($mulaiTujuan->lte($mulaiAsal)) {
                $this->errors[] = "Baris {$rowNumber}: Tahun ajaran kelas tujuan harus lebih tinggi dari tahun ajaran kelas asal";
                return false;
            }

            return [
                'mode' => self::MODE_PROMOTION,
                'siswa_id' => (int) $siswa->id,
                'kelas_asal_id' => (int) $kelasAsal->id,
                'kelas_tujuan_id' => (int) $kelasTujuan->id,
                'tahun_ajaran_tujuan_id' => (int) $kelasTujuan->tahun_ajaran_id,
                'keterangan' => 'Naik kelas massal via import manajemen kelas',
            ];
        }

        if ($kelasAktif) {
            $this->errors[] = "Baris {$rowNumber}: Mode Siswa Baru hanya untuk siswa yang belum memiliki kelas aktif";
            return false;
        }

        return [
            'mode' => self::MODE_NEW_STUDENT,
            'siswa_id' => (int) $siswa->id,
            'kelas_asal_id' => null,
            'kelas_tujuan_id' => (int) $kelasTujuan->id,
            'tahun_ajaran_tujuan_id' => (int) $kelasTujuan->tahun_ajaran_id,
            'keterangan' => 'Penetapan kelas awal via import manajemen kelas',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processPromotion(array $payload): void
    {
        if (($payload['mode'] ?? self::MODE_PROMOTION) === self::MODE_NEW_STUDENT) {
            $this->processNewStudentAssignment($payload);
            return;
        }

        SiswaTransisi::create([
            'siswa_id' => $payload['siswa_id'],
            'type' => 'naik_kelas',
            'kelas_asal_id' => $payload['kelas_asal_id'],
            'kelas_tujuan_id' => $payload['kelas_tujuan_id'],
            'tahun_ajaran_id' => $payload['tahun_ajaran_tujuan_id'],
            'tanggal_transisi' => $this->tanggalTransisi,
            'keterangan' => $payload['keterangan'],
            'processed_by' => $this->processedBy,
            'is_undone' => false,
            'can_undo' => true,
        ]);

        DB::table('kelas_siswa')
            ->where('siswa_id', $payload['siswa_id'])
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => 'pindah',
                'tanggal_keluar' => $this->tanggalTransisi,
                'keterangan' => $payload['keterangan'],
                'updated_at' => now(),
            ]);

        $existing = DB::table('kelas_siswa')
            ->where('siswa_id', $payload['siswa_id'])
            ->where('kelas_id', $payload['kelas_tujuan_id'])
            ->where('tahun_ajaran_id', $payload['tahun_ajaran_tujuan_id'])
            ->first();

        if ($existing) {
            DB::table('kelas_siswa')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'tanggal_masuk' => $this->tanggalTransisi,
                    'tanggal_keluar' => null,
                    'keterangan' => $payload['keterangan'],
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('kelas_siswa')->insert([
            'siswa_id' => $payload['siswa_id'],
            'kelas_id' => $payload['kelas_tujuan_id'],
            'tahun_ajaran_id' => $payload['tahun_ajaran_tujuan_id'],
            'tanggal_masuk' => $this->tanggalTransisi,
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'is_active' => true,
            'keterangan' => $payload['keterangan'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function processNewStudentAssignment(array $payload): void
    {
        $existing = DB::table('kelas_siswa')
            ->where('siswa_id', $payload['siswa_id'])
            ->where('kelas_id', $payload['kelas_tujuan_id'])
            ->where('tahun_ajaran_id', $payload['tahun_ajaran_tujuan_id'])
            ->first();

        if ($existing) {
            DB::table('kelas_siswa')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'tanggal_masuk' => $this->tanggalTransisi,
                    'tanggal_keluar' => null,
                    'keterangan' => $payload['keterangan'],
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('kelas_siswa')->insert([
            'siswa_id' => $payload['siswa_id'],
            'kelas_id' => $payload['kelas_tujuan_id'],
            'tahun_ajaran_id' => $payload['tahun_ajaran_tujuan_id'],
            'tanggal_masuk' => $this->tanggalTransisi,
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'is_active' => true,
            'keterangan' => $payload['keterangan'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeMode(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return match ($normalized) {
            'siswa_baru', 'baru' => self::MODE_NEW_STUDENT,
            'naik_kelas', 'naik' => self::MODE_PROMOTION,
            default => null,
        };
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return array{promoted:int,assigned_new:int,imported:int,skipped:int,errors:array<int,string>,total_processed:int}
     */
    public function getSummary(): array
    {
        return [
            'promoted' => $this->promoted,
            'assigned_new' => $this->assignedNew,
            'imported' => $this->promoted + $this->assignedNew,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'total_processed' => $this->promoted + $this->assignedNew + $this->skipped,
        ];
    }
}
