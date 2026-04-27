<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserImport implements ToCollection, WithHeadingRow
{
    private $imported = 0;
    private $errors = [];
    private $availableRoles;

    public function __construct()
    {
        // Get available roles from database
        $this->availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->whereIn('name', ['Guru', 'Staff', 'Wali Kelas'])
            ->pluck('name')
            ->toArray();
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                // Validasi data
                $validator = Validator::make($row->toArray(), [
                    'username' => 'required|string|max:50|unique:users',
                    'email' => 'required|string|email|max:255|unique:users',
                    'nama_lengkap' => 'required|string|max:100',
                    'role' => 'required|string|in:' . implode(',', $this->availableRoles),
                    'sub_role' => 'nullable|string',
                    'status_kepegawaian' => 'required|in:ASN,Honorer',
                    'nip' => [
                        'nullable',
                        'string',
                        'max:20',
                        'unique:users',
                        function ($attribute, $value, $fail) use ($row) {
                            if ($row['status_kepegawaian'] === 'ASN' && empty($value)) {
                                $fail('NIP wajib diisi untuk status kepegawaian ASN');
                            }
                        },
                    ]
                ]);

                if ($validator->fails()) {
                    $this->errors[] = "Baris {$row['username']}: " . implode(', ', $validator->errors()->all());
                    continue;
                }

                // Create user
                $user = User::create([
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'password' => Hash::make($row['password'] ?? 'password123'), // Default password jika tidak diisi
                    'nama_lengkap' => $row['nama_lengkap'],
                    'status_kepegawaian' => $row['status_kepegawaian'],
                    'nip' => $row['nip'],
                    'is_active' => true // Default active
                ]);

                // Assign role
                $user->assignRole($row['role']);

                // Assign sub role jika ada
                if (!empty($row['sub_role'])) {
                    $user->assignRole($row['sub_role']);
                }

                $this->imported++;
            } catch (\Exception $e) {
                Log::error('Error importing user: ' . $e->getMessage());
                $this->errors[] = "Error pada baris {$row['username']}: " . $e->getMessage();
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
}
