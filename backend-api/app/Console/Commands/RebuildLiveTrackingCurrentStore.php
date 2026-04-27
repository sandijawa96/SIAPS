<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LiveTrackingCurrentStoreService;
use App\Services\LiveTrackingSnapshotService;
use App\Support\RoleNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RebuildLiveTrackingCurrentStore extends Command
{
    private const FORCE_SESSION_CACHE_PREFIX = 'live_tracking:force_session:';
    private const FORCE_SESSION_USER_LIST_KEY = 'live_tracking:force_session_users';

    protected $signature = 'live-tracking:rebuild-current-store
        {--flush-only : Clear current-store keys without rebuilding}
        {--chunk=200 : Number of user records to hydrate per batch}';

    protected $description = 'Rebuild Redis live_tracking_current state from active live tracking snapshots.';

    public function handle(): int
    {
        $currentStore = app(LiveTrackingCurrentStoreService::class);
        $snapshotService = app(LiveTrackingSnapshotService::class);
        $chunkSize = max(50, (int) $this->option('chunk'));

        $deletedKeys = $currentStore->clearAll();
        if ((bool) $this->option('flush-only')) {
            $this->info("Cleared {$deletedKeys} live tracking current-store keys.");
            return self::SUCCESS;
        }

        $activeSessions = $this->resolveActiveForceSessions();
        $baselineCount = 0;
        User::query()
            ->select(['id', 'nama_lengkap', 'email', 'username'])
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->with([
                'kelas' => function ($kelasQuery): void {
                    $kelasQuery->select([
                        'kelas.id',
                        'kelas.nama_kelas',
                        'kelas.tingkat_id',
                        'kelas.wali_kelas_id',
                    ]);
                },
                'kelas.tingkat:id,nama',
                'kelas.waliKelas:id,nama_lengkap',
            ])
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $students) use ($currentStore, $activeSessions, &$baselineCount): void {
                foreach ($students as $student) {
                    $session = $activeSessions[(int) $student->id] ?? null;
                    $currentStore->upsertBaselineUser($student, [
                        'tracking_session_active' => !empty($session),
                        'tracking_session_expires_at' => $session['expires_at'] ?? null,
                    ]);
                    $baselineCount++;
                }
            }, 'id');

        $snapshots = $snapshotService->getMany();
        if ($snapshots === []) {
            $this->info("Rebuilt {$baselineCount} baseline student rows. No active snapshots found to overlay.");
            $this->line("Cleared keys: {$deletedKeys}");
            return self::SUCCESS;
        }

        $usersById = $this->resolveUsersForSnapshots($snapshots, $chunkSize);
        $rebuiltCount = 0;
        $missingUserCount = 0;

        foreach ($snapshots as $snapshot) {
            $userId = (int) ($snapshot['user_id'] ?? 0);
            $user = $usersById->get($userId);
            if (!$user instanceof User) {
                $missingUserCount++;
                continue;
            }

            $currentStore->upsertSnapshot($user, $snapshot);
            $rebuiltCount++;
        }

        $this->info("Rebuilt {$baselineCount} baseline student rows and overlaid {$rebuiltCount} active snapshots.");
        $this->line("Cleared keys: {$deletedKeys}");
        $this->line("Missing users skipped: {$missingUserCount}");

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     * @return Collection<int, User>
     */
    private function resolveUsersForSnapshots(array $snapshots, int $chunkSize): Collection
    {
        $users = collect();
        $userIds = collect($snapshots)
            ->pluck('user_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $userId): bool => $userId > 0)
            ->unique()
            ->values();

        foreach ($userIds->chunk($chunkSize) as $chunk) {
            $batch = User::query()
                ->select(['id', 'nama_lengkap', 'email', 'username'])
                ->with([
                    'kelas' => function ($kelasQuery): void {
                        $kelasQuery->select([
                            'kelas.id',
                            'kelas.nama_kelas',
                            'kelas.tingkat_id',
                            'kelas.wali_kelas_id',
                        ]);
                    },
                    'kelas.tingkat:id,nama',
                    'kelas.waliKelas:id,nama_lengkap',
                ])
                ->whereIn('id', $chunk->all())
                ->get()
                ->keyBy('id');

            $users = $users->merge($batch);
        }

        return $users;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveActiveForceSessions(): array
    {
        $active = [];
        $rawUserIds = Cache::get(self::FORCE_SESSION_USER_LIST_KEY, []);
        if (!is_array($rawUserIds)) {
            return $active;
        }

        foreach ($rawUserIds as $rawUserId) {
            $userId = (int) $rawUserId;
            if ($userId <= 0) {
                continue;
            }

            $session = Cache::get(self::FORCE_SESSION_CACHE_PREFIX . $userId);
            if (!is_array($session) || empty($session['expires_at'])) {
                continue;
            }

            $active[$userId] = $session;
        }

        return $active;
    }
}
