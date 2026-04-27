<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return User::with('roles')->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Username',
            'Email',
            'Nama Lengkap',
            'Role',
            'Status',
            'Tanggal Dibuat'
        ];
    }

    public function map($user): array
    {
        static $no = 1;

        return [
            $no++,
            $user->username,
            $user->email,
            $user->nama_lengkap,
            $user->roles->pluck('name')->implode(', '),
            $user->is_active ? 'Aktif' : 'Tidak Aktif',
            $user->created_at->format('d/m/Y H:i:s')
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
