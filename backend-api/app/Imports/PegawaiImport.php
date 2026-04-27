<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Cache;
use App\Support\RoleNames;

class PegawaiImport implements ToCollection, WithHeadingRow, WithStartRow, WithStyles, WithEvents
{
    private $imported = 0;
    private $errors = [];
    private $availableRoles;
    private $jobId;

    public function __construct($jobId = null)
    {
        $this->jobId = $jobId;

        // Align with PegawaiControllerExtended: all non-student, non-super-admin roles.
        $this->availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();
    }

    public function headingRow(): int
    {
        // Template and export files use row 4 as header after title rows.
        return 4;
    }

    public function startRow(): int
    {
        // Start from row 5 to skip title, date, empty row, and header
        return 5;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        // Base styles array
        $styles = [
            // Table header styling (will be row 4 after adding title rows)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ],

            // Data rows styling (starting from row 5)
            "A1:H{$lastRow}" => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],

            // Username column center alignment
            "A1:A{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Jenis Kelamin column center alignment
            "D1:D{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Status Kepegawaian column center alignment
            "G1:G{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Status column center alignment
            "H1:H{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ]
        ];

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Insert title row at the top
                $sheet->insertNewRowBefore(1, 3);

                // Add title
                $sheet->setCellValue('A1', 'AKUN PEGAWAI');
                $sheet->mergeCells('A1:H1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '2F4F4F']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // Add import date
                $sheet->setCellValue('A2', 'Tanggal Import: ' . date('d/m/Y H:i:s'));
                $sheet->mergeCells('A2:H2');
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '666666']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                    ]
                ]);

                // Add empty row
                $sheet->setCellValue('A3', '');

                // Set row heights
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(10);

                // Adjust header row (now row 4)
                $sheet->getRowDimension(4)->setRowHeight(25);
            }
        ];
    }

    public function collection(Collection $rows)
    {
        $totalRows = count($rows);
        $processedRows = 0;

        foreach ($rows as $index => $row) {
            try {
                $rowData = $row->toArray();

                // Skip empty rows
                if ($this->isEmptyRow($rowData)) {
                    continue;
                }

                // Update progress
                $processedRows++;
                if ($this->jobId) {
                    \Cache::put("import_progress_{$this->jobId}", [
                        'progress' => round(($processedRows / $totalRows) * 100),
                        'status' => 'processing',
                        'message' => "Memproses baris {$processedRows} dari {$totalRows}",
                        'total' => $totalRows,
                        'processed' => $processedRows
                    ], 300);
                }

                $mappedData = $this->mapRowData($rowData);
                $mappedData['username'] = trim((string) $mappedData['username']);
                $mappedData['email'] = trim((string) $mappedData['email']);
                $mappedData['nama_lengkap'] = trim((string) $mappedData['nama_lengkap']);
                $mappedData['jenis_kelamin'] = $this->normalizeJenisKelamin($mappedData['jenis_kelamin']);
                $mappedData['role'] = $this->normalizeRole($mappedData['role']);
                $mappedData['sub_role'] = $this->normalizeRole($mappedData['sub_role']);
                $mappedData['status_kepegawaian'] = $this->normalizeStatusKepegawaian($mappedData['status_kepegawaian']);
                $mappedData['status'] = $this->normalizeStatus($mappedData['status']);

                // Validasi data
                $validator = Validator::make($mappedData, [
                    'username' => 'required|string|max:50|unique:users',
                    'email' => 'required|string|email|max:255|unique:users',
                    'nama_lengkap' => 'required|string|max:100',
                    'jenis_kelamin' => 'required|in:L,P',
                    'role' => 'required|string|in:' . implode(',', $this->availableRoles),
                    'sub_role' => 'nullable|string|in:' . implode(',', $this->availableRoles),
                    'status_kepegawaian' => 'required|string|in:ASN,Honorer',
                    'status' => 'nullable|in:Aktif,Tidak Aktif'
                ]);

                if ($validator->fails()) {
                    $rowNumber = is_int($index) ? ($index + 5) : 5;
                    $this->errors[] = "Baris {$rowNumber} ({$mappedData['username']}): " . implode(', ', $validator->errors()->all());
                    continue;
                }

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
                    'username' => $mappedData['username'],
                    'email' => $mappedData['email'],
                    'password' => Hash::make('ICTsmanis2025$'), // Default password
                    'nama_lengkap' => $mappedData['nama_lengkap'],
                    'jenis_kelamin' => $mappedData['jenis_kelamin'],
                    'status_kepegawaian' => $mappedData['status_kepegawaian'],
                    'is_active' => ($mappedData['status'] === 'Aktif') ? true : false,
                    'created_by' => $createdBy, // Use authenticated user or admin, or null
                ]);

                // Assign role utama
                $user->assignRole($mappedData['role']);

                // Assign sub role jika ada
                if (!empty($mappedData['sub_role'])) {
                    $user->assignRole($mappedData['sub_role']);
                }

                $this->imported++;
            } catch (\Exception $e) {
                Log::error('Error importing pegawai: ' . $e->getMessage());
                $this->errors[] = "Error pada baris " . ($index + 5) . ": " . $e->getMessage();
            }
        }
    }

    public function getRowCount()
    {
        return $this->imported;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function mapRowData(array $rowData): array
    {
        $username = $this->valueFromKeys($rowData, ['username', 'nip', 'user_name']);
        $email = $this->valueFromKeys($rowData, ['email', 'e_mail']);
        $namaLengkap = $this->valueFromKeys($rowData, ['nama_lengkap', 'nama', 'name', 'nama_pegawai']);
        $jenisKelamin = $this->valueFromKeys($rowData, ['jenis_kelamin', 'gender', 'jenis_kelamin_l_p']);
        $role = $this->valueFromKeys($rowData, ['role', 'jabatan_role', 'role_utama']);
        $subRole = $this->valueFromKeys($rowData, ['sub_role', 'subrole', 'role_tambahan']);
        $statusKepegawaian = $this->valueFromKeys($rowData, ['status_kepegawaian', 'status_pegawai']);
        $status = $this->valueFromKeys($rowData, ['status', 'is_active', 'aktif']);

        if ($this->isMissingEssentialMappedData($username, $email, $namaLengkap, $rowData)) {
            $numeric = array_values($rowData);
            $offset = $this->hasLeadingNumberColumn($numeric) ? 1 : 0;

            $username = $username !== '' ? $username : ($numeric[$offset + 0] ?? '');
            $email = $email !== '' ? $email : ($numeric[$offset + 1] ?? '');
            $namaLengkap = $namaLengkap !== '' ? $namaLengkap : ($numeric[$offset + 2] ?? '');
            $jenisKelamin = $jenisKelamin !== '' ? $jenisKelamin : ($numeric[$offset + 3] ?? '');
            $role = $role !== '' ? $role : ($numeric[$offset + 4] ?? '');
            $subRole = $subRole !== '' ? $subRole : ($numeric[$offset + 5] ?? '');
            $statusKepegawaian = $statusKepegawaian !== '' ? $statusKepegawaian : ($numeric[$offset + 6] ?? '');
            $status = $status !== '' ? $status : ($numeric[$offset + 7] ?? 'Aktif');
        }

        return [
            'username' => $username,
            'email' => $email,
            'nama_lengkap' => $namaLengkap,
            'jenis_kelamin' => $jenisKelamin,
            'role' => $role,
            'sub_role' => $subRole,
            'status_kepegawaian' => $statusKepegawaian,
            'status' => $status !== '' ? $status : 'Aktif',
        ];
    }

    private function isMissingEssentialMappedData($username, $email, $namaLengkap, array $rowData): bool
    {
        if ($username !== '' && $email !== '' && $namaLengkap !== '') {
            return false;
        }

        foreach (array_keys($rowData) as $key) {
            if (!is_int($key) && !ctype_digit((string) $key)) {
                return false;
            }
        }

        return true;
    }

    private function hasLeadingNumberColumn(array $rowData): bool
    {
        $firstValue = trim((string) ($rowData[0] ?? ''));
        if ($firstValue === '') {
            return false;
        }

        return ctype_digit($firstValue) && strlen($firstValue) <= 4;
    }

    private function valueFromKeys(array $rowData, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $rowData)) {
                return trim((string) $rowData[$key]);
            }
        }

        return '';
    }

    private function normalizeJenisKelamin($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['l', 'laki-laki', 'laki laki', 'pria', 'male'], true)) {
            return 'L';
        }

        if (in_array($normalized, ['p', 'perempuan', 'wanita', 'female'], true)) {
            return 'P';
        }

        return strtoupper(trim((string) $value));
    }

    private function normalizeStatusKepegawaian($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['asn', 'pns'], true)) {
            return 'ASN';
        }

        if (in_array($normalized, ['honorer', 'honor', 'non asn', 'non-asn'], true)) {
            return 'Honorer';
        }

        return trim((string) $value);
    }

    private function normalizeStatus($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || in_array($normalized, ['aktif', 'active', '1', 'true'], true)) {
            return 'Aktif';
        }

        if (in_array($normalized, ['tidak aktif', 'nonaktif', 'inactive', '0', 'false'], true)) {
            return 'Tidak Aktif';
        }

        return trim((string) $value);
    }

    private function normalizeRole($value): string
    {
        $roleValue = trim((string) $value);
        if ($roleValue === '') {
            return '';
        }

        foreach ($this->availableRoles as $availableRole) {
            if (strcasecmp($availableRole, $roleValue) === 0) {
                return $availableRole;
            }
        }

        $normalized = RoleNames::normalize($roleValue);
        if ($normalized !== null) {
            foreach ($this->availableRoles as $availableRole) {
                if (strcasecmp($availableRole, $normalized) === 0) {
                    return $availableRole;
                }
            }

            foreach (RoleNames::aliasesFor($normalized) as $alias) {
                foreach ($this->availableRoles as $availableRole) {
                    if (strcasecmp($availableRole, $alias) === 0) {
                        return $availableRole;
                    }
                }
            }
        }

        return $roleValue;
    }
}
