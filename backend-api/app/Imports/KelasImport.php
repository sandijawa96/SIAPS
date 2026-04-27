<?php

namespace App\Imports;

use App\Models\Kelas;
use App\Models\Tingkat;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\WaliKelasRoleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Log;

class KelasImport implements ToCollection
{
    protected $errors = [];
    protected $imported = 0;
    protected $skipped = 0;

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;

                try {
                    // Debug: Log the row data first
                    $rowData = $row instanceof Collection ? $row->toArray() : (array)$row;
                    Log::info("Processing row {$rowNumber}", $rowData);

                    // Skip title, instruction, empty row, and header (first 4 rows)
                    if ($index < 4) {
                        Log::info("Skipping header/title row {$rowNumber}");
                        continue;
                    }

                    // Skip empty rows
                    if ($this->isEmptyRow($row)) {
                        Log::info("Skipping empty row {$rowNumber}");
                        continue;
                    }

                    // Skip instruction rows
                    if (empty(trim($rowData[1] ?? '')) && !empty(trim($rowData[10] ?? ''))) {
                        Log::info("Skipping instruction row {$rowNumber}");
                        continue;
                    }

                    // Skip rows where nama_kelas (column B/index 1) is empty
                    if (empty(trim($rowData[1] ?? ''))) {
                        Log::info("Skipping row {$rowNumber} - Nama kelas is empty");
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

                    // Create or update kelas
                    if ($this->createOrUpdateKelas($validatedData, $rowNumber)) {
                        $this->imported++;
                    }
                } catch (\Exception $e) {
                    $this->errors[] = "Baris {$rowNumber}: " . $e->getMessage();
                    $this->skipped++;
                    Log::error("Error processing row {$rowNumber}: " . $e->getMessage());
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Import failed: ' . $e->getMessage());
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
        // [No, Nama Kelas*, Tingkat*, Jurusan, Wali Kelas, Tahun Ajaran*, Kapasitas*, Keterangan, Status]
        $data = [
            'nama_kelas' => trim($row[1] ?? ''),       // Column B: Nama Kelas
            'tingkat' => trim($row[2] ?? ''),          // Column C: Tingkat
            'jurusan' => trim($row[3] ?? ''),          // Column D: Jurusan
            'wali_kelas' => trim($row[4] ?? ''),       // Column E: Wali Kelas
            'tahun_ajaran' => trim($row[5] ?? ''),     // Column F: Tahun Ajaran
            'kapasitas' => trim($row[6] ?? ''),        // Column G: Kapasitas
            'keterangan' => trim($row[7] ?? ''),       // Column H: Keterangan
            'status' => trim($row[8] ?? 'Aktif'),      // Column I: Status
        ];

        // Validation rules
        $rules = [
            'nama_kelas' => 'required|string|max:50',
            'tingkat' => 'required|string',
            'jurusan' => 'nullable|string|max:50',
            'wali_kelas' => 'nullable|string',
            'tahun_ajaran' => 'required|string',
            'kapasitas' => 'required|integer|min:1',
            'keterangan' => 'nullable|string',
            'status' => 'required|in:Aktif,Tidak Aktif',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            Log::warning("Validation failed for row {$rowNumber}:", ['errors' => $errors]);
            $this->errors[] = "Baris {$rowNumber}: " . implode(', ', $errors);
            return false;
        }

        // Validate tingkat exists
        $tingkat = Tingkat::where('nama', $data['tingkat'])
            ->orWhere('kode', $data['tingkat'])
            ->first();
        if (!$tingkat) {
            $this->errors[] = "Baris {$rowNumber}: Tingkat '{$data['tingkat']}' tidak ditemukan";
            return false;
        }

        // Validate tahun ajaran exists
        $tahunAjaran = TahunAjaran::where('nama', $data['tahun_ajaran'])->first();
        if (!$tahunAjaran) {
            $this->errors[] = "Baris {$rowNumber}: Tahun ajaran '{$data['tahun_ajaran']}' tidak ditemukan";
            return false;
        }

        // Check for duplicates (nama kelas + tahun ajaran harus unik)
        $existingKelas = Kelas::where('nama_kelas', $data['nama_kelas'])
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->first();
        if ($existingKelas) {
            $errorMsg = "Baris {$rowNumber}: Kelas '{$data['nama_kelas']}' sudah ada untuk tahun ajaran '{$data['tahun_ajaran']}'";
            Log::warning($errorMsg);
            $this->errors[] = $errorMsg;
            return false;
        }

        // Validate wali kelas if provided
        $waliKelasId = null;
        if (!empty($data['wali_kelas'])) {
            $waliKelas = User::where('nama_lengkap', $data['wali_kelas'])
                ->orWhere('nip', $data['wali_kelas'])
                ->first();
            if (!$waliKelas) {
                $this->errors[] = "Baris {$rowNumber}: Wali kelas '{$data['wali_kelas']}' tidak ditemukan";
                return false;
            }
            $waliKelasId = $waliKelas->id;
        }

        $data['tingkat_id'] = $tingkat->id;
        $data['tahun_ajaran_id'] = $tahunAjaran->id;
        $data['wali_kelas_id'] = $waliKelasId;
        $data['is_active'] = $data['status'] === 'Aktif';

        return $data;
    }

    protected function createOrUpdateKelas(array $data, int $rowNumber)
    {
        try {
            // Create kelas
            $kelas = Kelas::create([
                'nama_kelas' => $data['nama_kelas'],
                'tingkat_id' => $data['tingkat_id'],
                'jurusan' => $data['jurusan'] ?: null,
                'wali_kelas_id' => $data['wali_kelas_id'],
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'kapasitas' => (int)$data['kapasitas'],
                'keterangan' => $data['keterangan'] ?: null,
                'is_active' => $data['is_active'],
                'jumlah_siswa' => 0, // Default to 0
            ]);

            WaliKelasRoleService::ensureAssigned((int) ($data['wali_kelas_id'] ?? 0));

            Log::info("Successfully created kelas: {$data['nama_kelas']}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create kelas: {$data['nama_kelas']}, Error: " . $e->getMessage());
            throw $e;
        }
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
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'total_processed' => $this->imported + $this->skipped,
        ];
    }
}
