<?php

namespace App\Exports;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PegawaiExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths, ShouldAutoSize, WithEvents
{
    public function collection()
    {
        // Get available roles for pegawai only (same as in PegawaiControllerExtended)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->whereIn('name', ['Guru', 'Staff', 'Wali Kelas'])
            ->pluck('name')
            ->toArray();

        // Only export users with pegawai roles
        return User::role($availableRoles, 'web')->with('roles')->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Username',
            'Email',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Role',
            'Sub Role',
            'Status Kepegawaian',
            'Status',
            'Tanggal Dibuat'
        ];
    }

    public function map($user): array
    {
        static $no = 1;

        // Pisahkan role utama dan sub role
        $roles = $user->roles->pluck('name')->toArray();

        // Tentukan role utama berdasarkan prioritas
        $mainRole = null;
        if (in_array('Guru', $roles)) {
            $mainRole = 'Guru';
        } elseif (in_array('Staff', $roles)) {
            $mainRole = 'Staff';
        } elseif (in_array('Wali Kelas', $roles)) {
            $mainRole = 'Wali Kelas';
        }

        // Cari sub role yang bukan role utama
        $subRole = array_values(array_diff($roles, [$mainRole]))[0] ?? '';

        return [
            $no++,
            $user->username,
            $user->email,
            $user->nama_lengkap,
            $user->jenis_kelamin == 'L' ? 'Laki-laki' : ($user->jenis_kelamin == 'P' ? 'Perempuan' : '-'),
            $mainRole,
            $subRole,
            $user->status_kepegawaian ?? '-',
            $user->is_active ? 'Aktif' : 'Tidak Aktif',
            $user->created_at->format('d/m/Y H:i:s')
        ];
    }

    public function title(): string
    {
        return 'Data Pegawai';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No
            'B' => 20,  // Username
            'C' => 30,  // Email
            'D' => 25,  // Nama Lengkap
            'E' => 15,  // Jenis Kelamin
            'F' => 15,  // Role
            'G' => 15,  // Sub Role
            'H' => 15,  // Status Kepegawaian
            'I' => 12,  // Status
            'J' => 20,  // Tanggal Dibuat
        ];
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
            "A5:J{$lastRow}" => [
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

            // No column center alignment
            "A1:A{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Jenis Kelamin column center alignment
            "E1:E{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Status Kepegawaian column center alignment
            "H1:H{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ],

            // Status column center alignment
            "I1:I{$lastRow}" => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]
            ]
        ];

        // Add alternating row colors (starting from row 5)
        for ($row = 5; $row <= $lastRow; $row++) {
            if (($row - 4) % 2 == 0) {
                // Even data rows - light gray
                $styles["A{$row}:J{$row}"] = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA']
                    ]
                ];
            } else {
                // Odd data rows - white
                $styles["A{$row}:J{$row}"] = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFFFFF']
                    ]
                ];
            }
        }

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
                $sheet->mergeCells('A1:J1');
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

                // Add export date
                $sheet->setCellValue('A2', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
                $sheet->mergeCells('A2:J2');
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
}
