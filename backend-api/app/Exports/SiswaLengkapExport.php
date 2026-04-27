<?php

namespace App\Exports;

use App\Models\User;
use App\Support\RoleNames;
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class SiswaLengkapExport implements FromCollection, WithHeadings, WithMapping, WithCustomStartCell, WithEvents, WithColumnWidths, WithTitle
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
        return 'Daftar Peserta Didik';
    }

    public function startCell(): string
    {
        return 'A5';
    }

    public function collection()
    {
        $query = User::query()
            ->whereHas('roles', function (Builder $roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->with([
                'dataPribadiSiswa',
                'kelas' => function ($kelasQuery) {
                    $kelasQuery
                        ->with(['tingkat:id,nama', 'tahunAjaran:id,nama'])
                        ->withPivot('tahun_ajaran_id', 'status', 'tanggal_masuk', 'is_active');
                },
            ])
            ->orderBy('nama_lengkap', 'asc');

        $this->applyFilters($query);

        return $query->get();
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function headings(): array
    {
        $rowTop = [
            'No',
            'Nama',
            'NIPD',
            'JK',
            'NISN',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIK',
            'Agama',
            'Alamat',
            'RT',
            'RW',
            'Dusun',
            'Kelurahan',
            'Kecamatan',
            'Kode Pos',
            'Jenis Tinggal',
            'Alat Transportasi',
            'Telepon',
            'HP',
            'E-Mail',
            'SKHUN',
            'Penerima KPS',
            'No. KPS',
            'Data Ayah', '', '', '', '', '',
            'Data Ibu', '', '', '', '', '',
            'Data Wali', '', '', '', '', '',
            'Rombel Saat Ini',
            'No Peserta Ujian Nasional',
            'No Seri Ijazah',
            'Penerima KIP',
            'Nomor KIP',
            'Nama di KIP',
            'Nomor KKS',
            'No Registrasi Akta Lahir',
            'Bank',
            'Nomor Rekening Bank',
            'Rekening Atas Nama',
            'Layak PIP (usulan dari sekolah)',
            'Alasan Layak PIP',
            'Kebutuhan Khusus',
            'Sekolah Asal',
            'Anak ke-berapa',
            'Lintang',
            'Bujur',
            'No KK',
            'Berat Badan',
            'Tinggi Badan',
            'Lingkar Kepala',
            "Jml. Saudara\nKandung",
            "Jarak Rumah\nke Sekolah (KM)",
        ];

        $rowSub = array_merge(
            array_fill(0, 24, ''),
            ['Nama', 'Tahun Lahir', 'Jenjang Pendidikan', 'Pekerjaan', 'Penghasilan', 'NIK'],
            ['Nama', 'Tahun Lahir', 'Jenjang Pendidikan', 'Pekerjaan', 'Penghasilan', 'NIK'],
            ['Nama', 'Tahun Lahir', 'Jenjang Pendidikan', 'Pekerjaan', 'Penghasilan', 'NIK'],
            array_fill(0, 24, '')
        );

        return [$rowTop, $rowSub];
    }

    /**
     * @return array<string, float|int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 32.5,
            'C' => 17.332031,
            'D' => 5.164063,
            'E' => 11,
            'F' => 17.664063,
            'G' => 14.164063,
            'H' => 17.332031,
            'I' => 11,
            'J' => 45.664063,
            'K' => 3.5,
            'L' => 4.5,
            'M' => 20,
            'N' => 18.164063,
            'O' => 14.664063,
            'P' => 10,
            'Q' => 18.164063,
            'R' => 22.164063,
            'S' => 13.332031,
            'T' => 15.5,
            'U' => 25.832031,
            'V' => 20.5,
            'W' => 14.664063,
            'X' => 15.5,
            'Y' => 28.164063,
            'Z' => 12.5,
            'AA' => 20.164063,
            'AB' => 18,
            'AC' => 24.664063,
            'AD' => 25.332031,
            'AE' => 28.164063,
            'AF' => 12.5,
            'AG' => 20.164063,
            'AH' => 18.5,
            'AI' => 24.664063,
            'AJ' => 25.332031,
            'AK' => 28.164063,
            'AL' => 12.5,
            'AM' => 20.164063,
            'AN' => 18.5,
            'AO' => 18.5,
            'AP' => 22.5,
            'AQ' => 18.332031,
            'AR' => 17.5,
            'AS' => 16.832031,
            'AT' => 11,
            'AU' => 16.664063,
            'AV' => 16.664063,
            'AW' => 15.664063,
            'AX' => 18.164063,
            'AY' => 12,
            'AZ' => 20.832031,
            'BA' => 19.5,
            'BB' => 16.164063,
            'BC' => 18.164063,
            'BD' => 26.832031,
            'BE' => 35.832031,
            'BF' => 15,
            'BG' => 15,
            'BH' => 15,
            'BI' => 15,
            'BJ' => 15,
            'BK' => 15,
            'BL' => 15,
            'BM' => 15,
            'BN' => 15,
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

                $sheet->setCellValue('A1', 'Daftar Peserta Didik');
                $sheet->setCellValue('A2', $this->schoolName);
                $sheet->setCellValue('A3', $this->schoolRegion);
                $sheet->setCellValue('A4', 'Tanggal Unduh: ' . $this->downloadedAt);
                $sheet->setCellValue('C4', 'Pengunduh: ' . $this->downloadedBy);

                $sheet->mergeCells('A1:BN1');
                $sheet->mergeCells('A2:BN2');
                $sheet->mergeCells('A3:BN3');
                $sheet->mergeCells('A4:B4');
                $sheet->mergeCells('C4:BN4');

                for ($index = 1; $index <= 24; $index++) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    $sheet->mergeCells($column . '5:' . $column . '6');
                }

                $sheet->mergeCells('Y5:AD5');
                $sheet->mergeCells('AE5:AJ5');
                $sheet->mergeCells('AK5:AP5');

                for ($index = 43; $index <= 66; $index++) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    $sheet->mergeCells($column . '5:' . $column . '6');
                }

                $sheet->getStyle('A1:BN1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A2:BN2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A3:BN3')->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getStyle('A4:BN4')->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                $sheet->getStyle('A5:BN6')->applyFromArray([
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

                if ($highestDataRow >= 7) {
                    $sheet->getStyle('A7:BN' . $highestDataRow)->applyFromArray([
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
                $sheet->getRowDimension(5)->setRowHeight(26);
                $sheet->getRowDimension(6)->setRowHeight(24);
            },
        ];
    }

    /**
     * @param \App\Models\User $siswa
     * @return array<int, mixed>
     */
    public function map($siswa): array
    {
        $this->rowNumber++;

        $dataPribadi = $siswa->dataPribadiSiswa;
        $kelasAktif = $this->resolveActiveKelas($siswa);

        return [
            $this->rowNumber,
            $siswa->nama_lengkap,
            $dataPribadi?->nis ?? $siswa->nis,
            $this->normalizeGender($dataPribadi?->jenis_kelamin ?? $siswa->jenis_kelamin),
            $dataPribadi?->nisn ?? $siswa->nisn,
            $dataPribadi?->tempat_lahir ?? $siswa->tempat_lahir,
            $this->formatDate($dataPribadi?->tanggal_lahir ?? $siswa->tanggal_lahir),
            $siswa->nik,
            $dataPribadi?->agama ?? $siswa->agama,
            $dataPribadi?->alamat ?? $siswa->alamat,
            $dataPribadi?->rt ?? $siswa->rt,
            $dataPribadi?->rw ?? $siswa->rw,
            $dataPribadi?->dusun,
            $dataPribadi?->kelurahan ?? $siswa->kelurahan,
            $dataPribadi?->kecamatan ?? $siswa->kecamatan,
            $dataPribadi?->kode_pos ?? $siswa->kode_pos,
            $dataPribadi?->jenis_tinggal,
            $dataPribadi?->alat_transportasi,
            $dataPribadi?->no_telepon_rumah,
            $dataPribadi?->no_hp_siswa,
            $dataPribadi?->email_siswa ?? $siswa->email,
            $dataPribadi?->skhun,
            $this->booleanLabel($dataPribadi?->penerima_kps),
            $dataPribadi?->no_kps,
            $dataPribadi?->nama_ayah,
            $dataPribadi?->tahun_lahir_ayah,
            $dataPribadi?->pendidikan_ayah,
            $dataPribadi?->pekerjaan_ayah,
            $dataPribadi?->penghasilan_ayah,
            $dataPribadi?->nik_ayah,
            $dataPribadi?->nama_ibu,
            $dataPribadi?->tahun_lahir_ibu,
            $dataPribadi?->pendidikan_ibu,
            $dataPribadi?->pekerjaan_ibu,
            $dataPribadi?->penghasilan_ibu,
            $dataPribadi?->nik_ibu,
            $dataPribadi?->nama_wali,
            $dataPribadi?->tahun_lahir_wali,
            $dataPribadi?->pendidikan_wali,
            $dataPribadi?->pekerjaan_wali,
            $dataPribadi?->penghasilan_wali,
            $dataPribadi?->nik_wali,
            $kelasAktif?->nama_kelas,
            $dataPribadi?->no_peserta_ujian_nasional,
            $dataPribadi?->no_seri_ijazah,
            $this->booleanLabel($dataPribadi?->penerima_kip),
            $dataPribadi?->nomor_kip,
            $dataPribadi?->nama_di_kip,
            $dataPribadi?->nomor_kks,
            $dataPribadi?->no_registrasi_akta_lahir,
            $dataPribadi?->bank,
            $dataPribadi?->nomor_rekening_bank,
            $dataPribadi?->rekening_atas_nama,
            $this->booleanLabel($dataPribadi?->layak_pip),
            $dataPribadi?->alasan_layak_pip,
            $dataPribadi?->kebutuhan_khusus,
            $dataPribadi?->asal_sekolah,
            $dataPribadi?->anak_ke,
            $dataPribadi?->lintang,
            $dataPribadi?->bujur,
            $dataPribadi?->no_kk,
            $dataPribadi?->berat_badan,
            $dataPribadi?->tinggi_badan,
            $dataPribadi?->lingkar_kepala,
            $dataPribadi?->jumlah_saudara,
            $dataPribadi?->jarak_rumah_km,
        ];
    }

    private function applyFilters(Builder $query): void
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery->where('nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('nis', 'like', '%' . $search . '%')
                    ->orWhere('nisn', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $kelasId = $this->normalizePositiveInteger($this->filters['kelas_id'] ?? null);
        $tingkatId = $this->normalizePositiveInteger($this->filters['tingkat_id'] ?? null);
        $tahunAjaranId = $this->normalizePositiveInteger($this->filters['tahun_ajaran_id'] ?? null);

        if ($kelasId || $tingkatId || $tahunAjaranId) {
            $query->whereHas('kelas', function (Builder $kelasQuery) use ($kelasId, $tingkatId, $tahunAjaranId) {
                if ($kelasId) {
                    $kelasQuery->where('kelas.id', $kelasId);
                }

                if ($tingkatId) {
                    $kelasQuery->where('kelas.tingkat_id', $tingkatId);
                }

                if ($tahunAjaranId) {
                    $kelasQuery->where('kelas_siswa.tahun_ajaran_id', $tahunAjaranId);
                }

                $kelasQuery->where('kelas_siswa.is_active', true);
            });
        }

        $isActive = $this->normalizeBooleanFilter($this->filters['is_active'] ?? null);
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }
    }

    /**
     * @return mixed
     */
    private function resolveActiveKelas(User $siswa)
    {
        if (!$siswa->relationLoaded('kelas')) {
            return null;
        }

        $kelasAktif = $siswa->kelas->first(function ($kelas) {
            return (bool) ($kelas->pivot->is_active ?? false);
        });

        return $kelasAktif ?: $siswa->kelas->first();
    }

    /**
     * @param mixed $value
     */
    private function normalizePositiveInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
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

            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
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
