<?php

namespace App\Console\Commands;

use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use App\Services\PeriodeAkademikSetupService;
use Illuminate\Console\Command;

class BackfillPeriodeAkademikDefaults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'akademik:backfill-periode-default
        {--tahun-ajaran-id=* : Specific Tahun Ajaran IDs to process}
        {--execute : Apply changes to database. Without this, command runs in dry-run mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill default Periode Akademik for active (or explicitly selected) Tahun Ajaran that have no periods yet.';

    public function __construct(private readonly PeriodeAkademikSetupService $periodeAkademikSetupService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $inputIds = collect((array) $this->option('tahun-ajaran-id'))
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (!$execute) {
            $this->warn('Dry-run mode enabled. Use --execute to write changes.');
        }

        $query = TahunAjaran::query();
        if ($inputIds->isEmpty()) {
            $query->where('status', TahunAjaran::STATUS_ACTIVE);
            $this->info('Target mode: all active Tahun Ajaran');
        } else {
            $query->whereIn('id', $inputIds->all());
            $this->info('Target mode: explicit Tahun Ajaran IDs');
        }

        $targets = $query->orderBy('id')->get();

        if ($targets->isEmpty()) {
            $this->info('No target Tahun Ajaran found.');
            return self::SUCCESS;
        }

        $summary = [
            'total' => $targets->count(),
            'created' => 0,
            'would_create' => 0,
            'skipped_existing' => 0,
        ];

        foreach ($targets as $tahunAjaran) {
            $existingCount = PeriodeAkademik::where('tahun_ajaran_id', $tahunAjaran->id)->count();
            if ($existingCount > 0) {
                $summary['skipped_existing']++;
                $this->line("[SKIP] TA #{$tahunAjaran->id} {$tahunAjaran->nama} (existing periode: {$existingCount})");
                continue;
            }

            if (!$execute) {
                $summary['would_create']++;
                $this->line("[DRY] TA #{$tahunAjaran->id} {$tahunAjaran->nama} would get default periode");
                continue;
            }

            $result = $this->periodeAkademikSetupService->ensureDefaultForTahunAjaran($tahunAjaran, null);
            $summary['created'] += (int) ($result['created'] ?? 0);
            $this->line("[OK] TA #{$tahunAjaran->id} {$tahunAjaran->nama} created {$result['created']} periode");
        }

        $this->newLine();
        $this->info('Backfill summary:');
        $this->line('Total target: ' . $summary['total']);
        $this->line('Created: ' . $summary['created']);
        $this->line('Would create (dry-run): ' . $summary['would_create']);
        $this->line('Skipped (already has periode): ' . $summary['skipped_existing']);

        return self::SUCCESS;
    }
}

