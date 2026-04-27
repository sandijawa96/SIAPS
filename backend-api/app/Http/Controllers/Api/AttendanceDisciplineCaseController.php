<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDisciplineAlert;
use App\Models\AttendanceDisciplineCase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceDisciplineCaseController extends Controller
{
    public function export(Request $request): JsonResponse|StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $validator = Validator::make($request->all(), [
            'format' => 'required|string|in:csv,pdf',
            'status' => 'nullable|string|in:ready_for_parent_broadcast,parent_broadcast_sent',
            'parent_phone' => 'nullable|string|in:all,available,missing',
            'search' => 'nullable|string|max:100',
            'triggered_from' => 'nullable|date',
            'triggered_to' => 'nullable|date|after_or_equal:triggered_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter export histori alert pelanggaran tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $format = (string) $request->input('format');
        $rows = $this->buildCaseQuery($request)
            ->get()
            ->map(fn (AttendanceDisciplineCase $case) => $this->transformCaseRow($case))
            ->values();

        $timestamp = now()->format('Ymd-His');

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $output = fopen('php://output', 'w');
                if ($output === false) {
                    return;
                }

                fputcsv($output, [
                    'ID Kasus',
                    'Indikator',
                    'Siswa',
                    'Kelas',
                    'Periode',
                    'Tahun Ajaran',
                    'Nilai',
                    'Batas',
                    'Status',
                    'Nomor Orang Tua',
                    'Broadcast Terakhir',
                    'Waktu Trigger Pertama',
                    'Waktu Trigger Terakhir',
                ]);

                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['rule_label'] ?? '-',
                        $row['student']['name'] ?? '-',
                        $row['kelas']['name'] ?? '-',
                        $row['period_label'] ?? '-',
                        $row['tahun_ajaran_ref'] ?? '-',
                        sprintf('%s %s', $row['metric_value'] ?? 0, $row['metric_unit'] ?? ''),
                        sprintf('%s %s', $row['metric_limit'] ?? 0, $row['metric_unit'] ?? ''),
                        $row['status'] ?? '-',
                        ($row['parent_phone_available'] ?? false) ? 'Tersedia' : 'Belum tersedia',
                        $row['broadcast_campaign']['title'] ?? '-',
                        $row['first_triggered_at'] ?? '-',
                        $row['last_triggered_at'] ?? '-',
                    ]);
                }

                fclose($output);
            }, 'attendance-discipline-cases-' . $timestamp . '.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $pdf = Pdf::loadView('exports.attendance-discipline-cases-pdf', [
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('attendance-discipline-cases-' . $timestamp . '.pdf');
    }

    public function show(int $id): JsonResponse
    {
        $case = AttendanceDisciplineCase::query()
            ->with([
                'student:id,nama_lengkap,username,email,nis,nisn',
                'student.dataPribadiSiswa:id,user_id,no_hp_ortu,no_hp_ayah,no_hp_ibu,no_hp_wali',
                'kelas:id,nama_kelas',
                'broadcastCampaign:id,title,status,message_category,total_target,sent_count,failed_count,summary,audience,channels,created_at,sent_at',
            ])
            ->find($id);

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Kasus pelanggaran tidak ditemukan',
            ], 404);
        }

        $student = $case->student;
        $studentDetail = $student?->relationLoaded('dataPribadiSiswa') ? $student?->dataPribadiSiswa : null;
        $parentContacts = collect([
            ['label' => 'Orang Tua', 'value' => $studentDetail?->no_hp_ortu],
            ['label' => 'Ayah', 'value' => $studentDetail?->no_hp_ayah],
            ['label' => 'Ibu', 'value' => $studentDetail?->no_hp_ibu],
            ['label' => 'Wali', 'value' => $studentDetail?->no_hp_wali],
        ])->map(function (array $row) {
            $value = is_string($row['value']) ? trim($row['value']) : '';

            return [
                'label' => $row['label'],
                'value' => $value !== '' ? $value : null,
                'available' => $value !== '',
            ];
        })->values()->all();

        $alerts = AttendanceDisciplineAlert::query()
            ->with([
                'recipient:id,nama_lengkap,username,email',
                'notification:id,user_id,title,message,is_read,created_at',
                'whatsappNotification:id,phone_number,status,sent_at,error_message,metadata',
            ])
            ->where('user_id', (int) $case->user_id)
            ->where('rule_key', (string) $case->rule_key)
            ->when(
                trim((string) $case->period_key) !== '',
                function ($query) use ($case) {
                    $query
                        ->where('period_type', (string) $case->period_type)
                        ->where('period_key', (string) $case->period_key);
                },
                function ($query) use ($case) {
                    $query
                        ->where('semester', (string) $case->semester)
                        ->where('tahun_ajaran_ref', (string) $case->tahun_ajaran_ref);
                }
            )
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (AttendanceDisciplineAlert $alert) {
                return [
                    'id' => (int) $alert->id,
                    'audience' => (string) $alert->audience,
                    'recipient' => [
                        'id' => $alert->recipient?->id,
                        'name' => $alert->recipient?->nama_lengkap ?: $alert->recipient?->username ?: $alert->recipient?->email,
                    ],
                    'triggered_at' => optional($alert->triggered_at)?->toISOString(),
                    'notification' => $alert->notification ? [
                        'id' => (int) $alert->notification->id,
                        'title' => (string) $alert->notification->title,
                        'message' => (string) $alert->notification->message,
                        'is_read' => (bool) $alert->notification->is_read,
                        'created_at' => optional($alert->notification->created_at)?->toISOString(),
                    ] : null,
                    'whatsapp' => $alert->whatsappNotification ? [
                        'id' => (int) $alert->whatsappNotification->id,
                        'phone_number' => (string) $alert->whatsappNotification->phone_number,
                        'status' => (string) $alert->whatsappNotification->status,
                        'sent_at' => optional($alert->whatsappNotification->sent_at)?->toISOString(),
                        'error_message' => $alert->whatsappNotification->error_message,
                    ] : null,
                    'payload' => $alert->payload ?? [],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $case->id,
                'rule_key' => (string) $case->rule_key,
                'rule_label' => $this->resolveRuleLabel((string) $case->rule_key, $case->payload ?? []),
                'status' => (string) $case->status,
                'period_type' => (string) ($case->period_type ?? 'semester'),
                'period_key' => (string) ($case->period_key ?? ''),
                'period_label' => $this->resolvePeriodLabel($case),
                'semester' => (string) $case->semester,
                'tahun_ajaran_id' => $case->tahun_ajaran_id ? (int) $case->tahun_ajaran_id : null,
                'tahun_ajaran_ref' => (string) $case->tahun_ajaran_ref,
                'metric_value' => (int) $case->metric_value,
                'metric_limit' => (int) $case->metric_limit,
                'metric_unit' => $this->resolveMetricUnit($case),
                'first_triggered_at' => optional($case->first_triggered_at)?->toISOString(),
                'last_triggered_at' => optional($case->last_triggered_at)?->toISOString(),
                'student' => [
                    'id' => $student?->id,
                    'name' => $student?->nama_lengkap ?: $student?->username ?: $student?->email,
                    'username' => $student?->username,
                    'email' => $student?->email,
                    'nis' => $student?->nis,
                    'nisn' => $student?->nisn,
                ],
                'kelas' => [
                    'id' => $case->kelas?->id,
                    'name' => $case->kelas?->nama_kelas ?: ($case->payload['class_name'] ?? '-'),
                ],
                'payload' => $case->payload ?? [],
                'parent_contacts' => $parentContacts,
                'parent_phone_available' => collect($parentContacts)->contains(fn (array $row) => (bool) ($row['available'] ?? false)),
                'alerts' => $alerts,
                'broadcast_campaign' => $case->broadcastCampaign ? [
                    'id' => (int) $case->broadcastCampaign->id,
                    'title' => (string) $case->broadcastCampaign->title,
                    'status' => (string) $case->broadcastCampaign->status,
                    'message_category' => (string) ($case->broadcastCampaign->message_category ?? 'announcement'),
                    'total_target' => (int) ($case->broadcastCampaign->total_target ?? 0),
                    'sent_count' => (int) ($case->broadcastCampaign->sent_count ?? 0),
                    'failed_count' => (int) ($case->broadcastCampaign->failed_count ?? 0),
                    'summary' => is_array($case->broadcastCampaign->summary ?? null)
                        ? ($case->broadcastCampaign->summary['channels'] ?? [])
                        : [],
                    'audience' => is_array($case->broadcastCampaign->audience ?? null) ? $case->broadcastCampaign->audience : [],
                    'channels' => is_array($case->broadcastCampaign->channels ?? null) ? $case->broadcastCampaign->channels : [],
                    'created_at' => optional($case->broadcastCampaign->created_at)?->toISOString(),
                    'sent_at' => optional($case->broadcastCampaign->sent_at)?->toISOString(),
                ] : null,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:ready_for_parent_broadcast,parent_broadcast_sent',
            'parent_phone' => 'nullable|string|in:all,available,missing',
            'search' => 'nullable|string|max:100',
            'triggered_from' => 'nullable|date',
            'triggered_to' => 'nullable|date|after_or_equal:triggered_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter histori alert pelanggaran tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $this->buildCaseQuery($request);
        $summary = $this->buildCaseSummary(clone $query);

        $paginator = $query->paginate((int) $request->input('per_page', 10));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AttendanceDisciplineCase $case) => $this->transformCaseRow($case))
        );

        return response()->json([
            'success' => true,
            'data' => $paginator,
            'meta' => [
                'summary' => $summary,
            ],
        ]);
    }

    private function buildCaseQuery(Request $request): Builder
    {
        $query = AttendanceDisciplineCase::query()
            ->with([
                'student:id,nama_lengkap,username,email,nis,nisn',
                'student.dataPribadiSiswa:id,user_id,no_hp_ortu,no_hp_ayah,no_hp_ibu,no_hp_wali',
                'kelas:id,nama_kelas',
                'broadcastCampaign:id,title,status,message_category,total_target,sent_count,failed_count,summary,created_at,sent_at',
            ])
            ->orderByDesc('last_triggered_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $parentPhoneFilter = (string) $request->input('parent_phone', 'all');
        if ($parentPhoneFilter === 'available') {
            $query->whereHas('student.dataPribadiSiswa', $this->hasParentPhoneConstraint());
        } elseif ($parentPhoneFilter === 'missing') {
            $query->where(function (Builder $missingQuery) {
                $missingQuery
                    ->whereDoesntHave('student.dataPribadiSiswa')
                    ->orWhereDoesntHave('student.dataPribadiSiswa', $this->hasParentPhoneConstraint());
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $scoped) use ($search) {
                $scoped->whereHas('student', function (Builder $studentQuery) use ($search) {
                    $studentQuery
                        ->where('nama_lengkap', 'like', '%' . $search . '%')
                        ->orWhere('username', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('nis', 'like', '%' . $search . '%')
                        ->orWhere('nisn', 'like', '%' . $search . '%');
                })->orWhereHas('kelas', function (Builder $kelasQuery) use ($search) {
                    $kelasQuery->where('nama_kelas', 'like', '%' . $search . '%');
                });
            });
        }

        if ($request->filled('triggered_from')) {
            $query->whereDate('last_triggered_at', '>=', (string) $request->input('triggered_from'));
        }

        if ($request->filled('triggered_to')) {
            $query->whereDate('last_triggered_at', '<=', (string) $request->input('triggered_to'));
        }

        return $query;
    }

    private function buildCaseSummary(Builder $summaryQuery): array
    {
        return [
            'total' => (clone $summaryQuery)->count(),
            'ready_for_parent_broadcast' => (clone $summaryQuery)
                ->where('status', AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST)
                ->count(),
            'parent_broadcast_sent' => (clone $summaryQuery)
                ->where('status', AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT)
                ->count(),
            'parent_phone_available' => (clone $summaryQuery)
                ->whereHas('student.dataPribadiSiswa', $this->hasParentPhoneConstraint())
                ->count(),
            'parent_phone_missing' => (clone $summaryQuery)
                ->where(function (Builder $missingQuery) {
                    $missingQuery
                        ->whereDoesntHave('student.dataPribadiSiswa')
                        ->orWhereDoesntHave('student.dataPribadiSiswa', $this->hasParentPhoneConstraint());
                })
                ->count(),
        ];
    }

    private function hasParentPhoneConstraint(): \Closure
    {
        return function (Builder $studentDetailQuery): void {
            $studentDetailQuery->where(function (Builder $phoneQuery) {
                $phoneQuery
                    ->where(function (Builder $query) {
                        $query->whereNotNull('no_hp_ortu')->where('no_hp_ortu', '!=', '');
                    })
                    ->orWhere(function (Builder $query) {
                        $query->whereNotNull('no_hp_ayah')->where('no_hp_ayah', '!=', '');
                    })
                    ->orWhere(function (Builder $query) {
                        $query->whereNotNull('no_hp_ibu')->where('no_hp_ibu', '!=', '');
                    })
                    ->orWhere(function (Builder $query) {
                        $query->whereNotNull('no_hp_wali')->where('no_hp_wali', '!=', '');
                    });
            });
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCaseRow(AttendanceDisciplineCase $case): array
    {
        $student = $case->student;
        $parentPhoneAvailable = false;
        if ($student && $student->relationLoaded('dataPribadiSiswa') && $student->dataPribadiSiswa) {
            $parentPhoneAvailable = collect([
                $student->dataPribadiSiswa->no_hp_ortu,
                $student->dataPribadiSiswa->no_hp_ayah,
                $student->dataPribadiSiswa->no_hp_ibu,
                $student->dataPribadiSiswa->no_hp_wali,
            ])->contains(fn ($value) => is_string($value) && trim($value) !== '');
        }

        return [
            'id' => (int) $case->id,
            'rule_key' => (string) $case->rule_key,
            'rule_label' => $this->resolveRuleLabel((string) $case->rule_key, $case->payload ?? []),
            'status' => (string) $case->status,
            'period_type' => (string) ($case->period_type ?? 'semester'),
            'period_key' => (string) ($case->period_key ?? ''),
            'period_label' => $this->resolvePeriodLabel($case),
            'semester' => (string) $case->semester,
            'tahun_ajaran_id' => $case->tahun_ajaran_id ? (int) $case->tahun_ajaran_id : null,
            'tahun_ajaran_ref' => (string) $case->tahun_ajaran_ref,
            'metric_value' => (int) $case->metric_value,
            'metric_limit' => (int) $case->metric_limit,
            'metric_unit' => $this->resolveMetricUnit($case),
            'first_triggered_at' => optional($case->first_triggered_at)?->toISOString(),
            'last_triggered_at' => optional($case->last_triggered_at)?->toISOString(),
            'student' => [
                'id' => $student?->id,
                'name' => $student?->nama_lengkap ?: $student?->username ?: $student?->email,
                'nis' => $student?->nis,
                'nisn' => $student?->nisn,
            ],
            'kelas' => [
                'id' => $case->kelas?->id,
                'name' => $case->kelas?->nama_kelas ?: ($case->payload['class_name'] ?? '-'),
            ],
            'payload' => $case->payload ?? [],
            'parent_phone_available' => $parentPhoneAvailable,
            'broadcast_campaign' => $case->broadcastCampaign ? [
                'id' => (int) $case->broadcastCampaign->id,
                'title' => (string) $case->broadcastCampaign->title,
                'status' => (string) $case->broadcastCampaign->status,
                'message_category' => (string) ($case->broadcastCampaign->message_category ?? 'announcement'),
                'total_target' => (int) ($case->broadcastCampaign->total_target ?? 0),
                'sent_count' => (int) ($case->broadcastCampaign->sent_count ?? 0),
                'failed_count' => (int) ($case->broadcastCampaign->failed_count ?? 0),
                'summary' => is_array($case->broadcastCampaign->summary ?? null)
                    ? ($case->broadcastCampaign->summary['channels'] ?? [])
                    : [],
                'created_at' => optional($case->broadcastCampaign->created_at)?->toISOString(),
                'sent_at' => optional($case->broadcastCampaign->sent_at)?->toISOString(),
            ] : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRuleLabel(string $ruleKey, array $payload = []): string
    {
        $payloadLabel = trim((string) ($payload['rule_label'] ?? ''));
        if ($payloadLabel !== '') {
            return $payloadLabel;
        }

        return match ($ruleKey) {
            'monthly_late_limit' => 'Keterlambatan Bulanan',
            'semester_total_violation_limit' => 'Total Pelanggaran Semester',
            default => 'Alpha Semester',
        };
    }

    private function resolveMetricUnit(AttendanceDisciplineCase $case): string
    {
        $payload = is_array($case->payload) ? $case->payload : [];
        $payloadUnit = trim((string) ($payload['metric_unit'] ?? ''));
        if ($payloadUnit !== '') {
            return $payloadUnit;
        }

        return $case->rule_key === 'semester_alpha_limit' ? 'hari' : 'menit';
    }

    private function resolvePeriodLabel(AttendanceDisciplineCase $case): string
    {
        $explicit = trim((string) ($case->period_label ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $payload = is_array($case->payload) ? $case->payload : [];
        $payloadLabel = trim((string) ($payload['period_label'] ?? ''));
        if ($payloadLabel !== '') {
            return $payloadLabel;
        }

        $legacySemester = trim((string) ($payload['semester_label'] ?? $case->semester));
        $tahunAjaranRef = trim((string) $case->tahun_ajaran_ref);

        return trim($legacySemester . ' ' . $tahunAjaranRef);
    }
}
