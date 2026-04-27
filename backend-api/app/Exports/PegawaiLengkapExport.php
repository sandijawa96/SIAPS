<?php

namespace App\Exports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Spatie\Permission\Models\Role;

class PegawaiLengkapExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents, WithColumnWidths, WithTitle
{
    private int $rowNumber = 0;

    /**
     * @var array<string, mixed>
     */
    private array $filters;

    private string $schoolName;
    private string $schoolRegion;
    private string $downloadedBy;
    private string $downloadedAt;

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $meta
     */
    public function __construct(array $filters = [], array $meta = [])
    {
        $this->filters = $filters;
        $this->schoolName = (string) ($meta['school_name'] ?? 'SMAN 1 SUMBER');
        $this->schoolRegion = (string) ($meta['school_region'] ?? 'Kecamatan Kec. Sumber, Kabupaten Kab. Cirebon, Provinsi Prov. Jawa Barat');
        $this->downloadedBy = (string) ($meta['downloaded_by'] ?? '-');
        $this->downloadedAt = (string) ($meta['downloaded_at'] ?? now()->format('Y-m-d H:i:s'));
    }

    public function title(): string
    {
        return 'Daftar guru';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection()
    {
        $availableRoles = Role::query()
            ->where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        $query = User::query()
            ->role($availableRoles, 'web')
            ->with(['roles:id,name,display_name', 'dataKepegawaian'])
            ->orderBy('nama_lengkap', 'asc');

        $this->applyFilters($query);

        return $query->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'NUPTK',
            'JK',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIP',
            'Status Kepegawaian',
            'Jenis PTK',
            'Agama',
            'Alamat Jalan',
            'RT',
            'RW',
            'Nama Dusun',
            'Desa/Kelurahan',
            'Kecamatan',
            'Kode Pos',
            'Telepon',
            'HP',
            'Email',
            'Tugas Tambahan',
            'SK CPNS',
            'Tanggal CPNS',
            'SK Pengangkatan',
            'TMT Pengangkatan',
            'Lembaga Pengangkatan',
            'Pangkat Golongan',
            'Sumber Gaji',
            'Nama Ibu Kandung',
            'Status Perkawinan',
            'Nama Suami/Istri',
            'NIP Suami/Istri',
            'Pekerjaan Suami/Istri',
            'TMT PNS',
            'Sudah Lisensi Kepala Sekolah',
            'Pernah Diklat Kepengawasan',
            'Keahlian Braille',
            'Keahlian Bahasa Isyarat',
            'NPWP',
            'Nama Wajib Pajak',
            'Kewarganegaraan',
            'Bank',
            'Nomor Rekening Bank',
            'Rekening Atas Nama',
            'NIK',
            'No KK',
            'Karpeg',
            'Karis/Karsu',
            'Lintang',
            'Bujur',
            'NUKS',
        ];
    }

    /**
     * @return array<string, float|int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 5.5,
            'B' => 47.5,
            'C' => 18.832031,
            'D' => 4.832031,
            'E' => 16.832031,
            'F' => 16.5,
            'G' => 19.5,
            'H' => 21,
            'I' => 26.832031,
            'J' => 7.664063,
            'K' => 26.5,
            'L' => 5.664063,
            'M' => 5.664063,
            'N' => 13.5,
            'O' => 16.664063,
            'P' => 21.5,
            'Q' => 10,
            'R' => 20.664063,
            'S' => 18.832031,
            'T' => 23.664063,
            'U' => 19.664063,
            'V' => 15.664063,
            'W' => 14.5,
            'X' => 18.332031,
            'Y' => 20.164063,
            'Z' => 24.664063,
            'AA' => 19.332031,
            'AB' => 22.332031,
            'AC' => 20,
            'AD' => 19.5,
            'AE' => 18.332031,
            'AF' => 15.832031,
            'AG' => 23.832031,
            'AH' => 18.332031,
            'AI' => 30.164063,
            'AJ' => 30.164063,
            'AK' => 15.664063,
            'AL' => 24.5,
            'AM' => 23.664063,
            'AN' => 23.664063,
            'AO' => 18.832031,
            'AP' => 16,
            'AQ' => 23.332031,
            'AR' => 21.5,
            'AS' => 31.5,
            'AT' => 31.5,
            'AU' => 16,
            'AV' => 16,
            'AW' => 16,
            'AX' => 16,
            'AY' => 16,
        ];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestDataRow = $sheet->getHighestRow();

                $sheet->setCellValue('A1', 'Daftar Guru');
                $sheet->setCellValue('A2', $this->schoolName);
                $sheet->setCellValue('A3', $this->schoolRegion);
                $sheet->setCellValue('A4', 'Tanggal Unduh: ' . $this->downloadedAt);
                $sheet->setCellValue('C4', 'Pengunduh: ' . $this->downloadedBy);

                $sheet->mergeCells('A1:AY1');
                $sheet->mergeCells('A2:AY2');
                $sheet->mergeCells('A3:AY3');
                $sheet->mergeCells('A4:B4');
                $sheet->mergeCells('C4:AY4');

                $sheet->getStyle('A1:AY1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A2:AY2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A3:AY3')->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A4:AY4')->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                $sheet->getStyle('A5:AY5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF000000'],
                        ],
                    ],
                ]);

                if ($highestDataRow >= 6) {
                    $sheet->getStyle('A6:AY' . $highestDataRow)->applyFromArray([
                        'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ],
                    ]);
                }

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(20);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(5)->setRowHeight(24);
            },
        ];
    }

    /**
     * @param \App\Models\User $pegawai
     * @return array<int, mixed>
     */
    public function map($pegawai): array
    {
        $this->rowNumber++;

        $dataKepegawaian = $pegawai->dataKepegawaian;

        return [
            $this->rowNumber,
            $pegawai->nama_lengkap,
            $dataKepegawaian?->nuptk ?? $pegawai->nuptk,
            $this->normalizeGender($dataKepegawaian?->jenis_kelamin ?? $pegawai->jenis_kelamin),
            $dataKepegawaian?->tempat_lahir ?? $pegawai->tempat_lahir,
            $this->formatDate($dataKepegawaian?->tanggal_lahir ?? $pegawai->tanggal_lahir),
            $dataKepegawaian?->nip ?? $pegawai->nip,
            $dataKepegawaian?->status_kepegawaian ?? $pegawai->status_kepegawaian,
            $dataKepegawaian?->jenis_ptk,
            $dataKepegawaian?->agama ?? $pegawai->agama,
            $dataKepegawaian?->alamat_jalan ?? $dataKepegawaian?->alamat ?? $pegawai->alamat,
            $dataKepegawaian?->rt ?? $pegawai->rt,
            $dataKepegawaian?->rw ?? $pegawai->rw,
            $dataKepegawaian?->nama_dusun,
            $dataKepegawaian?->kelurahan ?? $pegawai->kelurahan,
            $dataKepegawaian?->kecamatan ?? $pegawai->kecamatan,
            $dataKepegawaian?->kode_pos ?? $pegawai->kode_pos,
            $dataKepegawaian?->no_telepon_kantor,
            $dataKepegawaian?->no_hp,
            $pegawai->email,
            $dataKepegawaian?->tugas_tambahan,
            $dataKepegawaian?->sk_cpns,
            $this->formatDate($dataKepegawaian?->tanggal_cpns),
            $dataKepegawaian?->sk_pengangkatan ?? $dataKepegawaian?->nomor_sk,
            $this->formatDate($dataKepegawaian?->tmt_pengangkatan),
            $dataKepegawaian?->lembaga_pengangkatan,
            $dataKepegawaian?->pangkat_golongan ?? $dataKepegawaian?->golongan,
            $dataKepegawaian?->sumber_gaji,
            $dataKepegawaian?->nama_ibu_kandung,
            $dataKepegawaian?->status_perkawinan ?? $dataKepegawaian?->status_pernikahan,
            $dataKepegawaian?->nama_pasangan,
            $dataKepegawaian?->nip_suami_istri,
            $dataKepegawaian?->pekerjaan_pasangan,
            $this->formatDate($dataKepegawaian?->tmt_pns),
            $this->booleanLabel($dataKepegawaian?->sudah_lisensi_kepala_sekolah),
            $this->booleanLabel($dataKepegawaian?->pernah_diklat_kepengawasan),
            $this->booleanLabel($dataKepegawaian?->keahlian_braille),
            $this->booleanLabel($dataKepegawaian?->keahlian_bahasa_isyarat),
            $dataKepegawaian?->npwp,
            $dataKepegawaian?->nama_wajib_pajak,
            $dataKepegawaian?->kewarganegaraan,
            $dataKepegawaian?->bank,
            $dataKepegawaian?->nomor_rekening_bank,
            $dataKepegawaian?->rekening_atas_nama,
            $pegawai->nik,
            $dataKepegawaian?->no_kk,
            $dataKepegawaian?->karpeg,
            $dataKepegawaian?->karis_karsu,
            $dataKepegawaian?->lintang,
            $dataKepegawaian?->bujur,
            $dataKepegawaian?->nuks,
        ];
    }

    private function applyFilters(Builder $query): void
    {
        $roleFilters = $this->normalizeStringFilter($this->filters['role'] ?? null);
        if (!empty($roleFilters)) {
            $query->whereHas('roles', function (Builder $roleQuery) use ($roleFilters) {
                $roleQuery->whereIn('name', $roleFilters);
            });
        }

        $statusFilters = $this->normalizeStatusKepegawaianFilter($this->filters['status_kepegawaian'] ?? null);
        if (!empty($statusFilters)) {
            $query->where(function (Builder $statusQuery) use ($statusFilters) {
                $statusQuery->whereIn('status_kepegawaian', $statusFilters)
                    ->orWhereHas('dataKepegawaian', function (Builder $dataQuery) use ($statusFilters) {
                        $dataQuery->whereIn('status_kepegawaian', $statusFilters);
                    });
            });
        }

        $isActive = $this->normalizeBooleanFilter($this->filters['is_active'] ?? null);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('dataKepegawaian', function (Builder $dataQuery) use ($search) {
                        $dataQuery->where('nuptk', 'like', '%' . $search . '%')
                            ->orWhere('nip', 'like', '%' . $search . '%');
                    });
            });
        }
    }

    /**
     * @param mixed $value
     */
    private function normalizeBooleanFilter($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }

            if ($value === 0) {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStringFilter($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $rawValues = is_array($value) ? $value : [(string) $value];
        $normalized = array_map(static function ($item): string {
            return trim((string) $item);
        }, $rawValues);

        return array_values(array_unique(array_filter($normalized, static function ($item): bool {
            return $item !== '';
        })));
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStatusKepegawaianFilter($value): array
    {
        $statusValues = $this->normalizeStringFilter($value);
        if (empty($statusValues)) {
            return [];
        }

        $normalized = [];
        foreach ($statusValues as $status) {
            $statusUpper = strtoupper($status);
            if (in_array($statusUpper, ['PNS', 'PPPK', 'ASN'], true)) {
                $normalized[] = 'ASN';
                continue;
            }

            if (strcasecmp($status, 'Honorer') === 0) {
                $normalized[] = 'Honorer';
                continue;
            }

            $normalized[] = $status;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $value
     */
    private function formatDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $throwable) {
            return (string) $value;
        }
    }

    private function normalizeGender(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        if (in_array($normalized, ['L', 'P'], true)) {
            return $normalized;
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function booleanLabel($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (bool) $value ? 'Ya' : 'Tidak';
    }
}
