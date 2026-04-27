<?php

namespace App\Services;

use App\Models\EventAkademik;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KalenderAkademikSyncService
{
    private const SOURCE_SYSTEM = 'event_akademik';

    public function syncEvent(EventAkademik $event, ?int $actorId = null): void
    {
        if ($event->jenis !== EventAkademik::JENIS_LIBUR) {
            return;
        }

        $sourceHash = $this->buildSourceHash($event);
        $targetPeserta = $this->buildTargetPeserta($event);

        $payload = [
            'nama_kegiatan' => $event->nama,
            'deskripsi' => $event->deskripsi,
            'tanggal_mulai' => $event->tanggal_mulai,
            'tanggal_selesai' => $event->tanggal_selesai ?? $event->tanggal_mulai,
            'jam_mulai' => $event->waktu_mulai,
            'jam_selesai' => $event->waktu_selesai,
            'jenis_kegiatan' => 'libur',
            'status_absensi' => 'libur',
            'target_peserta' => !empty($targetPeserta) ? json_encode($targetPeserta) : null,
            'lokasi' => $event->lokasi,
            'warna' => '#ef4444',
            'is_active' => (bool) $event->is_active,
            'created_by' => $actorId,
            'source_system' => self::SOURCE_SYSTEM,
            'source_event_id' => $event->id,
            'source_hash' => $sourceHash,
            'tahun_ajaran_id' => (int) $event->tahun_ajaran_id,
            'updated_at' => now(),
        ];

        $existing = DB::table('kalender_akademik')
            ->where('source_system', self::SOURCE_SYSTEM)
            ->where('source_event_id', $event->id)
            ->first();

        if ($existing) {
            DB::table('kalender_akademik')
                ->where('id', $existing->id)
                ->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('kalender_akademik')->insert($payload);
        }
    }

    public function removeEvent(int $eventId): void
    {
        DB::table('kalender_akademik')
            ->where('source_system', self::SOURCE_SYSTEM)
            ->where('source_event_id', $eventId)
            ->delete();
    }

    public function resyncByTahunAjaran(?int $tahunAjaranId = null, ?int $actorId = null): array
    {
        $query = EventAkademik::query()
            ->where('jenis', EventAkademik::JENIS_LIBUR);

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        $events = $query->get();
        $eventIds = $events->pluck('id')->map(fn($id) => (int) $id)->all();

        foreach ($events as $event) {
            $this->syncEvent($event, $actorId);
        }

        $cleanupQuery = DB::table('kalender_akademik')
            ->where('source_system', self::SOURCE_SYSTEM);

        if (!empty($eventIds)) {
            $cleanupQuery->whereNotIn('source_event_id', $eventIds);
        }

        if ($tahunAjaranId) {
            $cleanupQuery->where('tahun_ajaran_id', $tahunAjaranId);
        }

        $deleted = $cleanupQuery->delete();

        Log::info('Kalender akademik resync completed', [
            'tahun_ajaran_id' => $tahunAjaranId,
            'synced_count' => count($eventIds),
            'deleted_count' => $deleted,
        ]);

        return [
            'synced_count' => count($eventIds),
            'deleted_count' => $deleted,
        ];
    }

    public function getSyncStatus(?int $tahunAjaranId = null, int $limit = 50): array
    {
        $eventBase = EventAkademik::query()
            ->from('event_akademik as e')
            ->where('e.jenis', EventAkademik::JENIS_LIBUR);

        $kalenderBase = DB::table('kalender_akademik as k')
            ->where('k.source_system', self::SOURCE_SYSTEM);

        if ($tahunAjaranId) {
            $eventBase->where('e.tahun_ajaran_id', $tahunAjaranId);
            $kalenderBase->where('k.tahun_ajaran_id', $tahunAjaranId);
        }

        $totalEventLibur = (clone $eventBase)->count();
        $totalKalenderLinked = (clone $kalenderBase)->count();

        $missingInKalenderQuery = (clone $eventBase)
            ->leftJoin('kalender_akademik as k', function ($join) {
                $join->on('k.source_event_id', '=', 'e.id')
                    ->where('k.source_system', '=', self::SOURCE_SYSTEM);
            })
            ->whereNull('k.id');

        $missingInKalenderCount = (clone $missingInKalenderQuery)->count();
        $missingInKalender = $missingInKalenderQuery
            ->select([
                'e.id',
                'e.tahun_ajaran_id',
                'e.nama',
                'e.tanggal_mulai',
                'e.tanggal_selesai',
                'e.is_active',
            ])
            ->orderBy('e.tanggal_mulai')
            ->limit($limit)
            ->get();

        $orphanInKalenderQuery = DB::table('kalender_akademik as k')
            ->leftJoin('event_akademik as e', 'e.id', '=', 'k.source_event_id')
            ->where('k.source_system', self::SOURCE_SYSTEM)
            ->whereNull('e.id');

        if ($tahunAjaranId) {
            $orphanInKalenderQuery->where('k.tahun_ajaran_id', $tahunAjaranId);
        }

        $orphanInKalenderCount = (clone $orphanInKalenderQuery)->count();
        $orphanInKalender = $orphanInKalenderQuery
            ->select([
                'k.id',
                'k.tahun_ajaran_id',
                'k.source_event_id',
                'k.nama_kegiatan',
                'k.tanggal_mulai',
                'k.tanggal_selesai',
                'k.is_active',
            ])
            ->orderBy('k.tanggal_mulai')
            ->limit($limit)
            ->get();

        return [
            'summary' => [
                'tahun_ajaran_id' => $tahunAjaranId,
                'total_event_libur' => $totalEventLibur,
                'total_kalender_linked' => $totalKalenderLinked,
                'missing_in_kalender_count' => $missingInKalenderCount,
                'orphan_in_kalender_count' => $orphanInKalenderCount,
            ],
            'missing_in_kalender' => $missingInKalender,
            'orphan_in_kalender' => $orphanInKalender,
        ];
    }

    private function buildTargetPeserta(EventAkademik $event): array
    {
        $result = [];
        if (!empty($event->tingkat_id)) {
            $result['tingkat_id'] = (int) $event->tingkat_id;
        }
        if (!empty($event->kelas_id)) {
            $result['kelas_id'] = (int) $event->kelas_id;
        }

        return $result;
    }

    private function buildSourceHash(EventAkademik $event): string
    {
        return sprintf(
            'TA#%d|EV#%d|%s',
            (int) $event->tahun_ajaran_id,
            (int) $event->id,
            sha1($event->id . '|' . $event->nama . '|' . $event->tanggal_mulai . '|' . ($event->tanggal_selesai ?? $event->tanggal_mulai))
        );
    }
}
