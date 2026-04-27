<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Services\AttendanceTimeService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(private AttendanceTimeService $attendanceTimeService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $serverNow = now()->setTimezone(config('app.timezone'));
        $today = $serverNow->copy()->startOfDay();
        $startOfMonth = $serverNow->copy()->startOfMonth();
        $endOfMonth = $serverNow->copy()->endOfMonth();

        $todayAttendance = Absensi::query()
            ->where('user_id', $user->id)
            ->whereDate('tanggal', $today)
            ->first();

        $workingHours = $this->attendanceTimeService->getWorkingHours($user);
        $jamMasuk = (string) ($workingHours['jam_masuk'] ?? '07:00');
        $jamPulang = (string) ($workingHours['jam_pulang'] ?? '15:00');

        $monthSummary = [
            'hadir' => (int) Absensi::query()
                ->where('user_id', $user->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', 'hadir')
                ->count(),
            'telat' => (int) Absensi::query()
                ->where('user_id', $user->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', 'terlambat')
                ->count(),
            'izin' => (int) Absensi::query()
                ->where('user_id', $user->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->whereIn('status', ['izin', 'sakit'])
                ->count(),
            'alpha' => (int) Absensi::query()
                ->where('user_id', $user->id)
                ->whereBetween('tanggal', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->where('status', 'alpha')
                ->count(),
        ];

        $recentActivities = Absensi::query()
            ->where('user_id', $user->id)
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_masuk')
            ->limit(7)
            ->get()
            ->map(function (Absensi $item) {
                $status = strtolower((string) $item->status);
                $isLate = $status === 'terlambat';

                return [
                    'type' => $item->jam_pulang ? 'pulang' : 'masuk',
                    'time' => $item->jam_masuk
                        ? Carbon::parse((string) $item->jam_masuk)->format('H:i')
                        : ($item->jam_pulang ? Carbon::parse((string) $item->jam_pulang)->format('H:i') : null),
                    'date' => Carbon::parse((string) $item->tanggal)->format('d F Y'),
                    'status' => $isLate ? 'terlambat' : 'tepat_waktu',
                ];
            })
            ->values()
            ->all();

        $attendanceStatus = [
            'status' => $todayAttendance?->status ?? 'belum_absen',
            'time' => $todayAttendance?->jam_masuk
                ? Carbon::parse((string) $todayAttendance->jam_masuk)->format('H:i')
                : null,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'attendance_status' => $attendanceStatus,
                'schedule' => [
                    'jam_masuk' => $jamMasuk,
                    'jam_pulang' => $jamPulang,
                ],
                'month_summary' => $monthSummary,
                'recent_activities' => $recentActivities,
            ],
            'meta' => [
                'server_now' => $serverNow->toISOString(),
                'server_epoch_ms' => $serverNow->valueOf(),
                'server_date' => $serverNow->toDateString(),
                'timezone' => config('app.timezone'),
            ],
        ]);
    }
}
