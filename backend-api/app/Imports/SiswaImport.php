<?php

namespace App\Imports;

use App\Models\User;
use App\Models\DataPribadiSiswa;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Support\PhoneNumber;
use App\Support\RoleNames;

class SiswaImport implements ToCollection
{
    private const STUDENT_EMAIL_DOMAIN = 'sman1sumbercirebon.sch.id';

    protected $errors = [];
    protected $imported = 0;
    protected $updated = 0;
    protected $skipped = 0;
    protected $jobId;
    protected $totalRows = 0;
    protected $processedRows = 0;
    protected $importMode;
    protected $updateMode;
    protected $classAssigned = 0;
    protected $classSkippedLocked = 0;

    public function __construct($jobId = null, $importMode = 'auto', $updateMode = 'partial')
    {
        $this->jobId = $jobId;
        $this->importMode = $importMode;
        $this->updateMode = $updateMode;
    }

    public function collection(Collection $rows)
    {
        // Count total data rows (excluding headers)
        $this->totalRows = $rows->count() - 4; // Exclude first 4 rows (title, instruction, empty, header)

        // Update initial progress
        if ($this->jobId) {
            Cache::put("import_progress_{$this->jobId}", [
                'progress' => 5,
                'status' => 'processing',
                'message' => 'Memproses data...',
                'total' => $this->totalRows,
                'processed' => 0
            ], 300);
        }

        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1; // +1 karena index dimulai dari 0

                try {
                    // Debug: Log the row data first
                    $rowData = $row instanceof Collection ? $row->toArray() : (array)$row;
                    Log::info("Processing row {$rowNumber}", $rowData);

                    // Skip title, instruction, empty row, and header (first 4 rows)
                    // Row 1: Title, Row 2: Instruction, Row 3: Empty, Row 4: Header
                    if ($index < 4) {
                        Log::info("Skipping header/title row {$rowNumber}");
                        continue;
                    }

                    // Update progress
                    $this->processedRows++;
                    if ($this->jobId && $this->processedRows % 5 == 0) { // Update every 5 rows
                        $progress = min(95, 5 + (($this->processedRows / $this->totalRows) * 90));
                        Cache::put("import_progress_{$this->jobId}", [
                            'progress' => $progress,
                            'status' => 'processing',
                            'message' => "Memproses baris {$this->processedRows} dari {$this->totalRows}",
                            'total' => $this->totalRows,
                            'processed' => $this->processedRows
                        ], 300);
                    }

                    // Skip empty rows
                    if ($this->isEmptyRow($row)) {
                        Log::info("Skipping empty row {$rowNumber}");
                        continue;
                    }

                    // Skip rows that are just instructions/notes (check if first column is null/empty and last column has text)
                    if (empty(trim($rowData[1] ?? '')) && !empty(trim($rowData[12] ?? ''))) {
                        Log::info("Skipping instruction row {$rowNumber}");
                        continue; // This is likely an instruction row
                    }

                    // Skip rows where NIS (column B/index 1) is empty
                    if (empty(trim($rowData[1] ?? ''))) {
                        Log::info("Skipping row {$rowNumber} - NIS is empty");
                        continue;
                    }

                    // Validate row data
                    Log::info("Validating row {$rowNumber} data:", $rowData);
                    $validatedData = $this->validateRow($rowData, $rowNumber);

                    if (!$validatedData) {
                        Log::warning("Row {$rowNumber} validation failed");
                        $this->skipped++;
                        continue;
                    }
                    Log::info("Row {$rowNumber} validation passed:", $validatedData);

                    // Create or update user (counting is handled inside the method)
                    $this->createOrUpdateSiswa($validatedData, $rowNumber);
                } catch (\Exception $e) {
                    $this->errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->skipped++;
                    Log::error("Error processing row {$rowNumber}: " . $e->getMessage());
                }
            }

            DB::commit();

            // Final progress update
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", [
                    'progress' => 100,
                    'status' => 'completed',
                    'message' => 'Import selesai',
                    'total' => $this->totalRows,
                    'processed' => $this->processedRows
                ], 300);
            }
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Import failed: ' . $e->getMessage());

            // Update progress with error
            if ($this->jobId) {
                Cache::put("import_progress_{$this->jobId}", [
                    'progress' => 100,
                    'status' => 'error',
                    'message' => 'Import gagal: ' . $e->getMessage(),
                    'total' => $this->totalRows,
                    'processed' => $this->processedRows
                ], 300);
            }

            throw new \Exception('Import gagal: ' . $e->getMessage());
        }
    }

    protected function isEmptyRow($row)
    {
        $data = $row instanceof Collection ? $row->toArray() : (array)$row;
        return empty(array_filter($data, function ($value) {
            return !empty(trim($value));
        }));
    }

    protected function validateRow(array $row, int $rowNumber)
    {
        // Map Excel columns using numeric indices based on template
        $data = [
            'nis' => trim($row[1] ?? ''),           // Column B: NIS
            'nama_lengkap' => trim($row[2] ?? ''),  // Column C: Nama Lengkap
            'email' => trim($row[3] ?? ''),         // Column D: Email
            'nisn' => trim($row[4] ?? ''),          // Column E: NISN
            'tanggal_lahir' => trim($row[5] ?? ''), // Column F: Tanggal Lahir
            'jenis_kelamin' => trim($row[6] ?? ''), // Column G: Jenis Kelamin
            'kelas' => trim($row[7] ?? ''),         // Column H: Kelas
            'tahun_ajaran' => trim($row[8] ?? ''),  // Column I: Tahun Ajaran
            'tanggal_masuk' => trim($row[9] ?? ''), // Column J: Tanggal Masuk
            'no_telepon_orang_tua' => trim($row[10] ?? ''), // Column K: No. Telepon Orang Tua
            'status' => trim($row[11] ?? 'Aktif'),  // Column L: Status
        ];

        // Generate username from NIS
        $data['username'] = $data['nis'];
        $data['generated_email'] = $this->buildStudentEmailFromNis($data['nis']);

        // Validation rules
        $rules = [
            'nis' => 'required|string|max:20',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'nullable|string|max:255',
            'nisn' => 'required|string|max:20',
            'tanggal_lahir' => 'required|date_format:Y-m-d',
            'jenis_kelamin' => 'required|in:L,P',
            'kelas' => 'nullable|string',
            'tahun_ajaran' => 'nullable|string',
            'tanggal_masuk' => 'nullable|date_format:Y-m-d',
            'no_telepon_orang_tua' => 'required|string|max:20',
            'status' => 'required|in:Aktif,Tidak Aktif',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            Log::warning("Validation failed for row {$rowNumber}:", ['errors' => $errors]);
            $this->errors[] = "Baris {$rowNumber}: " . implode(', ', $errors);
            return false;
        }

        $normalizedParentPhone = PhoneNumber::normalizeIndonesianWa($data['no_telepon_orang_tua']);
        if ($normalizedParentPhone === '') {
            $this->errors[] = "Baris {$rowNumber}: No. Telepon Orang Tua harus nomor Indonesia valid (contoh: 628xxxxxxxxx)";
            return false;
        }
        $data['no_telepon_orang_tua'] = $normalizedParentPhone;

        // Check existing user by NIS first, then fallback by NISN
        $existingUser = User::where('nis', $data['nis'])->first();
        if (!$existingUser) {
            $existingUser = User::where('nisn', $data['nisn'])->first();
        }

        // Handle duplicates based on import mode
        if ($existingUser) {
            if ($this->importMode === 'create-only') {
                $this->errors[] = "Baris {$rowNumber}: NIS '{$data['nis']}' sudah digunakan oleh {$existingUser->nama_lengkap} (mode create-only)";
                return false;
            }
        } else {
            if ($this->importMode === 'update-only') {
                $this->errors[] = "Baris {$rowNumber}: NIS '{$data['nis']}' tidak ditemukan (mode update-only)";
                return false;
            }
        }

        // Ensure generated siswa identity fields are unique across all users.
        $usernameQuery = User::where('username', $data['username']);
        if ($existingUser) {
            $usernameQuery->where('id', '<>', $existingUser->id);
        }
        if ($usernameQuery->exists()) {
            $this->errors[] = "Baris {$rowNumber}: Username '{$data['username']}' sudah digunakan oleh user lain";
            return false;
        }

        $emailQuery = User::where('email', $data['generated_email']);
        if ($existingUser) {
            $emailQuery->where('id', '<>', $existingUser->id);
        }
        if ($emailQuery->exists()) {
            $this->errors[] = "Baris {$rowNumber}: Email '{$data['generated_email']}' sudah digunakan oleh user lain";
            return false;
        }

        $nisQuery = User::where('nis', $data['nis']);
        if ($existingUser) {
            $nisQuery->where('id', '<>', $existingUser->id);
        }
        if ($nisQuery->exists()) {
            $this->errors[] = "Baris {$rowNumber}: NIS '{$data['nis']}' sudah digunakan oleh user lain";
            return false;
        }

        $nisnQuery = User::where('nisn', $data['nisn']);
        if ($existingUser) {
            $nisnQuery->where('id', '<>', $existingUser->id);
        }
        if ($nisnQuery->exists()) {
            $this->errors[] = "Baris {$rowNumber}: NISN '{$data['nisn']}' sudah digunakan oleh user lain";
            return false;
        }

        // Kelas awal bersifat opsional.
        // Jika kolom kelas kosong, abaikan tahun ajaran + tanggal masuk agar import tetap berjalan.
        if (empty($data['kelas'])) {
            $data['kelas_id'] = null;
            $data['tahun_ajaran_id'] = null;
            $data['tanggal_masuk'] = null;
        } else {
            if (empty($data['tahun_ajaran']) || empty($data['tanggal_masuk'])) {
                $this->errors[] = "Baris {$rowNumber}: Jika kolom Kelas diisi, kolom Tahun Ajaran dan Tanggal Masuk wajib diisi";
                return false;
            }

            // Validate kelas exists
            $kelas = Kelas::where('nama_kelas', $data['kelas'])->first();
            if (!$kelas) {
                $this->errors[] = "Baris {$rowNumber}: Kelas '{$data['kelas']}' tidak ditemukan";
                return false;
            }

            // Validate tahun ajaran exists and provide clear error messages
            $tahunAjaran = TahunAjaran::where('nama', $data['tahun_ajaran'])->first();
            if (!$tahunAjaran) {
                $availableTahunAjaran = TahunAjaran::pluck('nama')->implode(', ');
                $this->errors[] = "Baris {$rowNumber}: Tahun ajaran '{$data['tahun_ajaran']}' tidak valid. Tahun ajaran yang tersedia: {$availableTahunAjaran}";
                return false;
            }

            // Validate tanggal_masuk sesuai rentang tahun ajaran
            $tanggalMasuk = Carbon::parse($data['tanggal_masuk']);
            $tahunMulai = Carbon::parse($tahunAjaran->tanggal_mulai);
            $tahunSelesai = Carbon::parse($tahunAjaran->tanggal_selesai);

            if ($tanggalMasuk->lt($tahunMulai) || $tanggalMasuk->gt($tahunSelesai)) {
                $this->errors[] = "Baris {$rowNumber}: Tanggal masuk ({$data['tanggal_masuk']}) tidak sesuai dengan rentang tahun ajaran {$tahunAjaran->nama}. Rentang yang valid: {$tahunAjaran->tanggal_mulai} s/d {$tahunAjaran->tanggal_selesai}";
                return false;
            }

            // Add warning if tahun ajaran is not active (but still allow import)
            if (!$this->isTahunAjaranActive($tahunAjaran)) {
                Log::warning("Warning: Importing to inactive tahun ajaran {$tahunAjaran->nama} on row {$rowNumber}");
                // Store warning in cache for UI display
                if ($this->jobId) {
                    $warnings = Cache::get("import_warnings_{$this->jobId}", []);
                    $warnings[] = "Baris {$rowNumber}: Data diimpor ke tahun ajaran {$tahunAjaran->nama} yang tidak aktif";
                    Cache::put("import_warnings_{$this->jobId}", $warnings, 300);
                }
            }

            $data['kelas_id'] = $kelas->id;
            $data['tahun_ajaran_id'] = $tahunAjaran->id;
        }

        $data['email'] = $data['generated_email'];

        return $data;
    }

    protected function createOrUpdateSiswa(array $data, int $rowNumber)
    {
        try {
            // Check if student exists by NIS, fallback NISN
            $existingUser = User::where('nis', $data['nis'])->first();
            if (!$existingUser) {
                $existingUser = User::where('nisn', $data['nisn'])->first();
            }

            // Handle based on import mode
            if ($existingUser !== null) {
                // Skip if create-only mode
                if ($this->importMode === 'create-only') {
                    Log::info("Skipping existing student with NIS: {$data['nis']} (create-only mode)");
                    $this->skipped++;
                    return false;
                }

                // Update existing user
                if ($this->importMode === 'auto' || $this->importMode === 'update-only') {
                    $this->updated++;
                    return $this->updateExistingSiswa($existingUser, $data, $rowNumber);
                }
            } else {
                // Skip if update-only mode
                if ($this->importMode === 'update-only') {
                    Log::info("Skipping non-existent student with NIS: {$data['nis']} (update-only mode)");
                    $this->skipped++;
                    return false;
                }

                // Create new user
                $this->imported++;
                return $this->createNewSiswa($data, $rowNumber);
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Failed to process student with NIS: {$data['nis']}, Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function createNewSiswa(array $data, int $rowNumber)
    {
        // Generate password from tanggal_lahir (DDMMYYYY format)
        $tanggalLahir = Carbon::parse($data['tanggal_lahir']);
        $password = $tanggalLahir->format('dmY');

        // Get the current authenticated user ID, or set to null if not available
        $createdBy = auth()->id();

        // If no authenticated user, try to find the first admin user
        if (!$createdBy) {
            $adminUser = User::whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::flattenAliases([
                    RoleNames::SUPER_ADMIN,
                    RoleNames::ADMIN,
                ]));
            })->first();
            $createdBy = $adminUser ? $adminUser->id : null;
        }

        // Create user
        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'nama_lengkap' => $data['nama_lengkap'],
            'nis' => $data['nis'],
            'nisn' => $data['nisn'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'password' => Hash::make($password),
            'is_active' => $data['status'] === 'Aktif',
            'created_by' => $createdBy,
        ]);

        // Assign role siswa
        $user->assignRole('Siswa');

        // Create data pribadi siswa
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'tanggal_lahir' => $data['tanggal_lahir'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'no_hp_ayah' => $data['no_telepon_orang_tua'],
            'status' => 'aktif'
        ]);

        // Attach kelas awal jika data kelas diisi lengkap
        if (!empty($data['kelas_id']) && !empty($data['tahun_ajaran_id']) && !empty($data['tanggal_masuk'])) {
            $this->activateOrCreateKelasMembership(
                $user->id,
                (int) $data['kelas_id'],
                (int) $data['tahun_ajaran_id'],
                (string) $data['tanggal_masuk'],
                'Kelas awal melalui import siswa'
            );
            $this->classAssigned++;
        }

        Log::info("Successfully created student with NIS: {$data['nis']}");
        return true;
    }

    protected function updateExistingSiswa(User $user, array $data, int $rowNumber)
    {
        $updateData = [];

        // In full update mode, update all fields
        if ($this->updateMode === 'full') {
            $updateData = [
                'username' => $data['username'],
                'email' => $data['email'],
                'nama_lengkap' => $data['nama_lengkap'],
                'nis' => $data['nis'],
                'nisn' => $data['nisn'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'is_active' => $data['status'] === 'Aktif',
            ];
        }
        // In partial update mode, only update non-empty fields
        else {
            $updateData['username'] = $data['username'];
            $updateData['email'] = $data['email'];
            if (!empty($data['nis'])) $updateData['nis'] = $data['nis'];
            if (!empty($data['nama_lengkap'])) $updateData['nama_lengkap'] = $data['nama_lengkap'];
            if (!empty($data['nisn'])) $updateData['nisn'] = $data['nisn'];
            if (!empty($data['jenis_kelamin'])) $updateData['jenis_kelamin'] = $data['jenis_kelamin'];
            if (isset($data['status'])) $updateData['is_active'] = $data['status'] === 'Aktif';
        }

        // Update user if there are changes
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Update data pribadi siswa
        if ($user->dataPribadiSiswa) {
            $dataPribadiFields = [];

            if ($this->updateMode === 'full') {
                $dataPribadiFields = [
                    'tanggal_lahir' => $data['tanggal_lahir'],
                    'jenis_kelamin' => $data['jenis_kelamin'],
                    'no_hp_ayah' => $data['no_telepon_orang_tua'],
                ];
            } else {
                if (!empty($data['tanggal_lahir'])) $dataPribadiFields['tanggal_lahir'] = $data['tanggal_lahir'];
                if (!empty($data['jenis_kelamin'])) $dataPribadiFields['jenis_kelamin'] = $data['jenis_kelamin'];
                if (!empty($data['no_telepon_orang_tua'])) $dataPribadiFields['no_hp_ayah'] = $data['no_telepon_orang_tua'];
            }

            if (!empty($dataPribadiFields)) {
                $user->dataPribadiSiswa->update($dataPribadiFields);
            }
        }

        // Set kelas awal hanya jika siswa belum pernah punya kelas.
        if (!empty($data['kelas_id']) && !empty($data['tahun_ajaran_id']) && !empty($data['tanggal_masuk'])) {
            $alreadyHasClass = DB::table('kelas_siswa')
                ->where('siswa_id', $user->id)
                ->exists();

            if (!$alreadyHasClass) {
                $this->activateOrCreateKelasMembership(
                    $user->id,
                    (int) $data['kelas_id'],
                    (int) $data['tahun_ajaran_id'],
                    (string) $data['tanggal_masuk'],
                    'Kelas awal melalui import siswa'
                );
                $this->classAssigned++;
            } else {
                Log::info("Skipping class update for NIS {$data['nis']} because class is locked (already assigned)");
                $this->classSkippedLocked++;
            }
        }

        Log::info("Successfully updated student with NIS: {$data['nis']}");
        return true;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getImported()
    {
        return $this->imported;
    }

    public function getSkipped()
    {
        return $this->skipped;
    }

    public function hasErrors()
    {
        return !empty($this->errors);
    }

    public function getSummary()
    {
        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'class_assigned' => $this->classAssigned,
            'class_skipped_locked' => $this->classSkippedLocked,
            'errors' => $this->errors,
            'total_processed' => $this->imported + $this->updated + $this->skipped,
            'success' => $this->imported + $this->updated,
            'failed' => $this->skipped
        ];
    }

    private function isTahunAjaranActive(TahunAjaran $tahunAjaran): bool
    {
        return $tahunAjaran->status === TahunAjaran::STATUS_ACTIVE || (bool) $tahunAjaran->is_active;
    }

    private function buildStudentEmailFromNis(string $nis): string
    {
        return strtolower(trim($nis)) . '@' . self::STUDENT_EMAIL_DOMAIN;
    }

    private function activateOrCreateKelasMembership(
        int $siswaId,
        int $kelasId,
        int $tahunAjaranId,
        string $tanggalMasuk,
        ?string $keterangan = null
    ): void {
        $existing = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->first();

        if ($existing) {
            DB::table('kelas_siswa')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'tanggal_masuk' => $tanggalMasuk,
                    'tanggal_keluar' => null,
                    'keterangan' => $keterangan,
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('kelas_siswa')->insert([
            'siswa_id' => $siswaId,
            'kelas_id' => $kelasId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'tanggal_masuk' => $tanggalMasuk,
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'is_active' => true,
            'keterangan' => $keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
