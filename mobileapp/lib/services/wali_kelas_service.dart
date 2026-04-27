import '../models/login_response.dart';
import 'api_service.dart';

class WaliClassSummary {
  final int id;
  final String namaKelas;
  final String? tingkatNama;
  final int jumlahSiswa;
  final int hadirHariIni;
  final int tidakHadirHariIni;
  final int izinPending;

  const WaliClassSummary({
    required this.id,
    required this.namaKelas,
    required this.tingkatNama,
    required this.jumlahSiswa,
    required this.hadirHariIni,
    required this.tidakHadirHariIni,
    required this.izinPending,
  });
}

class WaliStudentAttendanceEntry {
  final int userId;
  final String nama;
  final String? nisn;
  final String status;
  final String validationStatus;
  final bool hasWarning;
  final String? warningSummary;
  final int fraudFlagsCount;
  final String? keterangan;

  const WaliStudentAttendanceEntry({
    required this.userId,
    required this.nama,
    required this.nisn,
    required this.status,
    required this.validationStatus,
    required this.hasWarning,
    required this.warningSummary,
    required this.fraudFlagsCount,
    required this.keterangan,
  });
}

class WaliClassLeaveEntry {
  final int id;
  final String studentName;
  final String? nisn;
  final String jenisIzin;
  final String? jenisIzinLabel;
  final String status;
  final String? statusLabel;
  final DateTime? tanggalMulai;
  final DateTime? tanggalSelesai;

  const WaliClassLeaveEntry({
    required this.id,
    required this.studentName,
    required this.nisn,
    required this.jenisIzin,
    required this.jenisIzinLabel,
    required this.status,
    required this.statusLabel,
    required this.tanggalMulai,
    required this.tanggalSelesai,
  });
}

class WaliFraudFlagSummary {
  final String flagKey;
  final String label;
  final String? severity;
  final int total;

  const WaliFraudFlagSummary({
    required this.flagKey,
    required this.label,
    required this.severity,
    required this.total,
  });
}

class WaliFraudFollowUpStudent {
  final int userId;
  final String studentName;
  final String? studentIdentifier;
  final int totalAssessments;
  final int warningAttempts;
  final int precheckWarningAttempts;
  final int submitWarningAttempts;
  final DateTime? lastAssessmentAt;

  const WaliFraudFollowUpStudent({
    required this.userId,
    required this.studentName,
    required this.studentIdentifier,
    required this.totalAssessments,
    required this.warningAttempts,
    required this.precheckWarningAttempts,
    required this.submitWarningAttempts,
    required this.lastAssessmentAt,
  });
}

class WaliFraudAssessment {
  final int id;
  final String? assessmentDate;
  final String validationStatus;
  final String validationStatusLabel;
  final bool hasWarning;
  final String? warningSummary;
  final int fraudFlagsCount;
  final String? decisionReason;
  final String? recommendedAction;
  final bool isBlocking;
  final String studentName;
  final String? studentIdentifier;
  final List<String> signalLabels;
  final DateTime? createdAt;

  const WaliFraudAssessment({
    required this.id,
    required this.assessmentDate,
    required this.validationStatus,
    required this.validationStatusLabel,
    required this.hasWarning,
    required this.warningSummary,
    required this.fraudFlagsCount,
    required this.decisionReason,
    required this.recommendedAction,
    required this.isBlocking,
    required this.studentName,
    required this.studentIdentifier,
    required this.signalLabels,
    required this.createdAt,
  });
}

class WaliFraudConfig {
  final String rolloutMode;
  final bool warnUser;
  final bool warningOnly;

  const WaliFraudConfig({
    required this.rolloutMode,
    required this.warnUser,
    required this.warningOnly,
  });
}

class WaliFraudSummaryData {
  final int totalAssessments;
  final int warningCount;
  final int uniqueStudents;
  final WaliFraudConfig config;
  final List<WaliFraudFlagSummary> topFlags;
  final List<WaliFraudFollowUpStudent> followUpStudents;
  final List<WaliFraudAssessment> recentWarningAssessments;

  const WaliFraudSummaryData({
    required this.totalAssessments,
    required this.warningCount,
    required this.uniqueStudents,
    required this.config,
    required this.topFlags,
    required this.followUpStudents,
    required this.recentWarningAssessments,
  });
}

class WaliSecurityIssueSummary {
  final String key;
  final String label;
  final int total;

  const WaliSecurityIssueSummary({
    required this.key,
    required this.label,
    required this.total,
  });
}

class WaliSecurityStudentSummaryData {
  final int totalStudents;
  final int studentsNeedFollowUp;
  final int studentsWithOpenCases;
  final int studentsDone;
  final int studentsWithRepeatViolation;
  final int totalOpenCases;
  final int totalResolvedCases;

  const WaliSecurityStudentSummaryData({
    required this.totalStudents,
    required this.studentsNeedFollowUp,
    required this.studentsWithOpenCases,
    required this.studentsDone,
    required this.studentsWithRepeatViolation,
    required this.totalOpenCases,
    required this.totalResolvedCases,
  });
}

class WaliSecurityStudentRow {
  final int userId;
  final String studentName;
  final String? studentIdentifier;
  final String operationalStatus;
  final String operationalStatusLabel;
  final String? operationalStatusDescription;
  final String? violationSequenceLabel;
  final int securityEventsCount;
  final int fraudAssessmentsCount;
  final int warningEventsCount;
  final int warningAssessmentsCount;
  final int totalWarnings;
  final int blockedEvents;
  final int flaggedEvents;
  final int mockLocationEvents;
  final int deviceEvents;
  final int openCases;
  final int resolvedCases;
  final DateTime? latestActivityAt;
  final String? lastEventLabel;
  final String? recommendation;
  final bool needsFollowUp;
  final List<WaliSecurityIssueSummary> topIssues;

  const WaliSecurityStudentRow({
    required this.userId,
    required this.studentName,
    required this.studentIdentifier,
    required this.operationalStatus,
    required this.operationalStatusLabel,
    required this.operationalStatusDescription,
    required this.violationSequenceLabel,
    required this.securityEventsCount,
    required this.fraudAssessmentsCount,
    required this.warningEventsCount,
    required this.warningAssessmentsCount,
    required this.totalWarnings,
    required this.blockedEvents,
    required this.flaggedEvents,
    required this.mockLocationEvents,
    required this.deviceEvents,
    required this.openCases,
    required this.resolvedCases,
    required this.latestActivityAt,
    required this.lastEventLabel,
    required this.recommendation,
    required this.needsFollowUp,
    required this.topIssues,
  });
}

class WaliSecurityCase {
  final int id;
  final String caseNumber;
  final String status;
  final String statusLabel;
  final String priority;
  final String priorityLabel;
  final String summary;
  final String studentName;
  final String? studentIdentifier;
  final int itemsCount;
  final int evidenceCount;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? resolvedAt;

  const WaliSecurityCase({
    required this.id,
    required this.caseNumber,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.priorityLabel,
    required this.summary,
    required this.studentName,
    required this.studentIdentifier,
    required this.itemsCount,
    required this.evidenceCount,
    required this.createdAt,
    required this.updatedAt,
    required this.resolvedAt,
  });
}

class WaliClassDetailData {
  final int id;
  final String namaKelas;
  final String? tingkatNama;
  final int jumlahSiswa;
  final int hadirHariIni;
  final int tidakHadirHariIni;
  final int izinPending;
  final double persentaseKehadiran;
  final int totalHadir;
  final int totalTidakHadir;
  final List<Map<String, dynamic>> students;
  final List<WaliStudentAttendanceEntry> absensiDetail;
  final List<WaliClassLeaveEntry> izinList;
  final WaliFraudSummaryData fraudSummary;
  final List<WaliFraudAssessment> fraudAssessments;
  final WaliSecurityStudentSummaryData securityStudentSummary;
  final List<WaliSecurityStudentRow> securityStudents;
  final List<WaliSecurityCase> activeSecurityCases;

  const WaliClassDetailData({
    required this.id,
    required this.namaKelas,
    required this.tingkatNama,
    required this.jumlahSiswa,
    required this.hadirHariIni,
    required this.tidakHadirHariIni,
    required this.izinPending,
    required this.persentaseKehadiran,
    required this.totalHadir,
    required this.totalTidakHadir,
    required this.students,
    required this.absensiDetail,
    required this.izinList,
    required this.fraudSummary,
    required this.fraudAssessments,
    required this.securityStudentSummary,
    required this.securityStudents,
    required this.activeSecurityCases,
  });
}

class WaliKelasService {
  WaliKelasService._();
  static final WaliKelasService _instance = WaliKelasService._();
  factory WaliKelasService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<List<WaliClassSummary>>> getMyClasses() async {
    try {
      final response = await _apiService.get('/monitoring-kelas/kelas');
      final rawList = response.data is List
          ? response.data as List<dynamic>
          : (response.data is Map<String, dynamic> &&
                  (response.data as Map<String, dynamic>)['data'] is List
              ? (response.data as Map<String, dynamic>)['data'] as List<dynamic>
              : const <dynamic>[]);

      final baseClasses = rawList
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList();

      final List<WaliClassSummary> summaries = [];
      for (final row in baseClasses) {
        final id = _parseInt(row['id']);
        if (id <= 0) {
          continue;
        }

        final tingkat = _asMap(row['tingkat']);

        summaries.add(
          WaliClassSummary(
            id: id,
            namaKelas: (row['nama_kelas'] ?? '-').toString(),
            tingkatNama:
                (tingkat?['nama_tingkat'] ?? tingkat?['nama'])?.toString(),
            jumlahSiswa: _parseInt(row['jumlah_siswa']),
            hadirHariIni: _parseInt(row['hadir_hari_ini']),
            tidakHadirHariIni: _parseInt(row['tidak_hadir_hari_ini']),
            izinPending: _parseInt(row['izin_pending']),
          ),
        );
      }

      return ApiResponse<List<WaliClassSummary>>(
        success: true,
        message: 'Daftar monitoring kelas berhasil diambil',
        data: summaries,
      );
    } on ApiException catch (e) {
      return ApiResponse<List<WaliClassSummary>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<List<WaliClassSummary>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<WaliClassDetailData>> getClassDetailBundle(
    int classId,
  ) async {
    try {
      final responses = await Future.wait([
        _apiService.get('/monitoring-kelas/kelas/$classId'),
        _apiService.get('/monitoring-kelas/kelas/$classId/absensi'),
        _apiService.get('/monitoring-kelas/kelas/$classId/statistik'),
        _apiService.get('/monitoring-kelas/kelas/$classId/izin'),
        _apiService.get('/monitoring-kelas/kelas/$classId/fraud-assessments/summary'),
        _apiService.get(
          '/monitoring-kelas/kelas/$classId/fraud-assessments',
          queryParameters: const <String, dynamic>{'per_page': '6'},
        ),
      ]);

      final detailResponse = responses[0];
      final absensiResponse = responses[1];
      final statistikResponse = responses[2];
      final izinResponse = responses[3];
      final fraudSummaryResponse = responses[4];
      final fraudAssessmentsResponse = responses[5];

      final detailBody =
          detailResponse.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final kelas = _asMap(detailBody['kelas']) ?? <String, dynamic>{};
      final tingkat = _asMap(kelas['tingkat']);
      final rawStudents = _asList(kelas['siswa']);

      final absensiBody =
          absensiResponse.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawAbsensi = _asList(absensiBody['detail']);

      final statistikBody =
          statistikResponse.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawIzin = izinResponse.data is List
          ? izinResponse.data as List<dynamic>
          : (izinResponse.data is Map<String, dynamic> &&
                  (izinResponse.data as Map<String, dynamic>)['data'] is List
              ? (izinResponse.data as Map<String, dynamic>)['data']
                  as List<dynamic>
              : const <dynamic>[]);

      final fraudSummaryBody =
          fraudSummaryResponse.data as Map<String, dynamic>? ??
              <String, dynamic>{};
      final fraudSummaryData =
          _asMap(fraudSummaryBody['data']) ?? <String, dynamic>{};
      final fraudSummary = _parseFraudSummary(fraudSummaryData);

      final fraudAssessmentsBody =
          fraudAssessmentsResponse.data as Map<String, dynamic>? ??
              <String, dynamic>{};
      final fraudAssessmentsData =
          _asMap(fraudAssessmentsBody['data']) ?? <String, dynamic>{};
      final rawFraudAssessments = _asList(
        (_asMap(fraudAssessmentsData['assessments']) ?? fraudAssessmentsData)['data'],
      );

      final securityResponses = await Future.wait([
        _getOptionalMap(
          '/monitoring-kelas/kelas/$classId/security-students',
          queryParameters: const <String, dynamic>{
            'student_scope': 'needs_case',
            'per_page': '8',
          },
        ),
        _getOptionalMap(
          '/monitoring-kelas/kelas/$classId/security-cases',
          queryParameters: const <String, dynamic>{
            'case_scope': 'active',
            'per_page': '8',
          },
        ),
      ]);
      final securityStudentsBody = securityResponses[0];
      final securityStudentsData =
          _asMap(securityStudentsBody['data']) ?? <String, dynamic>{};
      final securityStudentSummary =
          _parseSecurityStudentSummary(securityStudentsData);
      final rawSecurityStudents = _asList(securityStudentsData['students']);

      final securityCasesBody = securityResponses[1];
      final securityCasesData =
          _asMap(securityCasesBody['data']) ?? <String, dynamic>{};
      final rawSecurityCases = _asList(
        (_asMap(securityCasesData['cases']) ?? securityCasesData)['data'],
      );

      return ApiResponse<WaliClassDetailData>(
        success: true,
        message: 'Detail monitoring kelas berhasil diambil',
        data: WaliClassDetailData(
          id: classId,
          namaKelas: (kelas['nama_kelas'] ?? '-').toString(),
          tingkatNama:
              (tingkat?['nama_tingkat'] ?? tingkat?['nama'])?.toString(),
          jumlahSiswa: rawStudents.length,
          hadirHariIni: _parseInt(detailBody['hadir_hari_ini']),
          tidakHadirHariIni: _parseInt(detailBody['tidak_hadir_hari_ini']),
          izinPending: _parseInt(detailBody['izin_pending']),
          persentaseKehadiran: _parseDouble(
                statistikBody['persentase_kehadiran'],
              ) ??
              0,
          totalHadir: _parseInt(statistikBody['total_hadir']),
          totalTidakHadir: _parseInt(statistikBody['total_tidak_hadir']),
          students: rawStudents
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList(),
          absensiDetail: rawAbsensi
              .whereType<Map>()
              .map((item) => _parseAttendanceEntry(Map<String, dynamic>.from(item)))
              .toList(),
          izinList: rawIzin
              .whereType<Map>()
              .map((item) => _parseLeaveEntry(Map<String, dynamic>.from(item)))
              .toList(),
          fraudSummary: fraudSummary,
          fraudAssessments: rawFraudAssessments
              .whereType<Map>()
              .map((item) => _parseFraudAssessment(Map<String, dynamic>.from(item)))
              .toList(),
          securityStudentSummary: securityStudentSummary,
          securityStudents: rawSecurityStudents
              .whereType<Map>()
              .map((item) =>
                  _parseSecurityStudentRow(Map<String, dynamic>.from(item)))
              .toList(),
          activeSecurityCases: rawSecurityCases
              .whereType<Map>()
              .map((item) =>
                  _parseSecurityCase(Map<String, dynamic>.from(item)))
              .toList(),
        ),
      );
    } on ApiException catch (e) {
      return ApiResponse<WaliClassDetailData>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<WaliClassDetailData>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  WaliStudentAttendanceEntry _parseAttendanceEntry(Map<String, dynamic> row) {
    final user = _asMap(row['user']) ?? <String, dynamic>{};

    return WaliStudentAttendanceEntry(
      userId: _parseInt(row['user_id']),
      nama: (user['nama_lengkap'] ?? '-').toString(),
      nisn: user['nisn']?.toString(),
      status: (row['status'] ?? '-').toString(),
      validationStatus: (row['validation_status'] ?? 'valid').toString(),
      hasWarning: _parseBool(row['has_warning']) ||
          (row['validation_status']?.toString() == 'warning'),
      warningSummary: row['warning_summary']?.toString(),
      fraudFlagsCount: _parseInt(row['fraud_flags_count']),
      keterangan: row['keterangan']?.toString(),
    );
  }

  WaliClassLeaveEntry _parseLeaveEntry(Map<String, dynamic> row) {
    final user = _asMap(row['user']) ?? <String, dynamic>{};

    return WaliClassLeaveEntry(
      id: _parseInt(row['id']),
      studentName: (user['nama_lengkap'] ?? '-').toString(),
      nisn: user['nisn']?.toString(),
      jenisIzin: (row['jenis_izin'] ?? '-').toString(),
      jenisIzinLabel: (row['jenis_izin_label'] ?? row['jenis_izin'])?.toString(),
      status: (row['status'] ?? '-').toString(),
      statusLabel: (row['status_label'] ?? row['status'])?.toString(),
      tanggalMulai: _parseDateTime(row['tanggal_mulai']),
      tanggalSelesai: _parseDateTime(row['tanggal_selesai']),
    );
  }

  WaliFraudSummaryData _parseFraudSummary(Map<String, dynamic> data) {
    final config = _asMap(data['config']) ?? <String, dynamic>{};
    final summary = _asMap(data['summary']) ?? <String, dynamic>{};

    return WaliFraudSummaryData(
      totalAssessments: _parseInt(summary['total_assessments']),
      warningCount: _parseInt(summary['warning_count']),
      uniqueStudents: _parseInt(summary['unique_students']),
      config: WaliFraudConfig(
        rolloutMode: (config['rollout_mode'] ?? 'warning_mode').toString(),
        warnUser: _parseBool(config['warn_user']),
        warningOnly: _parseBool(config['warning_only']) ||
            _parseBool(config['allow_submit_with_security_warnings']),
      ),
      topFlags: _asList(summary['top_flags'])
          .whereType<Map>()
          .map((item) => WaliFraudFlagSummary(
                flagKey: (item['flag_key'] ?? '-').toString(),
                label: (item['label'] ?? item['flag_key'] ?? '-').toString(),
                severity: item['severity']?.toString(),
                total: _parseInt(item['total']),
              ))
          .toList(),
      followUpStudents: _asList(summary['follow_up_students'])
          .whereType<Map>()
          .map((item) => WaliFraudFollowUpStudent(
                userId: _parseInt(item['user_id']),
                studentName: (item['student_name'] ?? '-').toString(),
                studentIdentifier: item['student_identifier']?.toString(),
                totalAssessments: _parseInt(item['total_assessments']),
                warningAttempts: _parseInt(item['warning_attempts']),
                precheckWarningAttempts:
                    _parseInt(item['precheck_warning_attempts']),
                submitWarningAttempts:
                    _parseInt(item['submit_warning_attempts']),
                lastAssessmentAt: _parseDateTime(item['last_assessment_at']),
              ))
          .toList(),
      recentWarningAssessments:
          _asList(summary['recent_warning_assessments'])
          .whereType<Map>()
          .map((item) => _parseFraudAssessment(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }

  WaliFraudAssessment _parseFraudAssessment(Map<String, dynamic> item) {
    final student = _asMap(item['student']) ?? <String, dynamic>{};
    final flags = _asList(item['flags']);
    final signalLabels = flags
        .whereType<Map>()
        .map((flag) => (flag['label'] ?? flag['flag_key'] ?? '').toString())
        .where((label) => label.trim().isNotEmpty)
        .toList();

    return WaliFraudAssessment(
      id: _parseInt(item['id']),
      assessmentDate: item['assessment_date']?.toString(),
      validationStatus: (item['validation_status'] ?? 'valid').toString(),
      validationStatusLabel:
          (item['validation_status_label'] ?? 'Valid').toString(),
      hasWarning: _parseBool(item['has_warning']) ||
          (item['validation_status']?.toString() == 'warning'),
      warningSummary: item['warning_summary']?.toString(),
      fraudFlagsCount: _parseInt(item['fraud_flags_count']),
      decisionReason: item['decision_reason']?.toString(),
      recommendedAction: item['recommended_action']?.toString(),
      isBlocking: _parseBool(item['is_blocking']),
      studentName: (student['name'] ?? '-').toString(),
      studentIdentifier: student['identifier']?.toString(),
      signalLabels: signalLabels,
      createdAt: _parseDateTime(item['created_at']),
    );
  }

  WaliSecurityStudentSummaryData _parseSecurityStudentSummary(
    Map<String, dynamic> data,
  ) {
    final summary = _asMap(data['summary']) ?? <String, dynamic>{};

    return WaliSecurityStudentSummaryData(
      totalStudents: _parseInt(summary['total_students']),
      studentsNeedFollowUp: _parseInt(summary['students_need_follow_up']),
      studentsWithOpenCases: _parseInt(summary['students_with_open_cases']),
      studentsDone: _parseInt(summary['students_done']),
      studentsWithRepeatViolation:
          _parseInt(summary['students_with_repeat_violation']),
      totalOpenCases: _parseInt(summary['total_open_cases']),
      totalResolvedCases: _parseInt(summary['total_resolved_cases']),
    );
  }

  WaliSecurityStudentRow _parseSecurityStudentRow(Map<String, dynamic> item) {
    final student = _asMap(item['student']) ?? <String, dynamic>{};

    return WaliSecurityStudentRow(
      userId: _parseInt(item['user_id'] ?? student['user_id']),
      studentName: (student['name'] ?? '-').toString(),
      studentIdentifier: student['identifier']?.toString(),
      operationalStatus:
          (item['operational_status'] ?? 'needs_case').toString(),
      operationalStatusLabel:
          (item['operational_status_label'] ?? 'Perlu Kasus').toString(),
      operationalStatusDescription:
          item['operational_status_description']?.toString(),
      violationSequenceLabel: item['violation_sequence_label']?.toString(),
      securityEventsCount: _parseInt(item['security_events_count']),
      fraudAssessmentsCount: _parseInt(item['fraud_assessments_count']),
      warningEventsCount: _parseInt(item['warning_events_count']),
      warningAssessmentsCount: _parseInt(item['warning_assessments_count']),
      totalWarnings: _parseInt(item['total_warnings']),
      blockedEvents: _parseInt(item['blocked_events']),
      flaggedEvents: _parseInt(item['flagged_events']),
      mockLocationEvents: _parseInt(item['mock_location_events']),
      deviceEvents: _parseInt(item['device_events']),
      openCases: _parseInt(item['open_cases']),
      resolvedCases: _parseInt(item['resolved_cases']),
      latestActivityAt: _parseDateTime(item['latest_activity_at']),
      lastEventLabel: item['last_event_label']?.toString(),
      recommendation: item['recommendation']?.toString(),
      needsFollowUp: _parseBool(item['needs_follow_up']),
      topIssues: _asList(item['top_issues'])
          .whereType<Map>()
          .map(
            (issue) => WaliSecurityIssueSummary(
              key: (issue['key'] ?? '-').toString(),
              label: (issue['label'] ?? issue['key'] ?? '-').toString(),
              total: _parseInt(issue['total']),
            ),
          )
          .toList(),
    );
  }

  WaliSecurityCase _parseSecurityCase(Map<String, dynamic> item) {
    final student = _asMap(item['student']) ?? <String, dynamic>{};

    return WaliSecurityCase(
      id: _parseInt(item['id']),
      caseNumber: (item['case_number'] ?? '-').toString(),
      status: (item['status'] ?? 'open').toString(),
      statusLabel: (item['status_label'] ?? 'Terbuka').toString(),
      priority: (item['priority'] ?? 'medium').toString(),
      priorityLabel: (item['priority_label'] ?? 'Sedang').toString(),
      summary: (item['summary'] ?? 'Tindak lanjut keamanan presensi siswa.')
          .toString(),
      studentName: (student['name'] ?? '-').toString(),
      studentIdentifier: student['identifier']?.toString(),
      itemsCount: _parseInt(item['items_count']),
      evidenceCount: _parseInt(item['evidence_count']),
      createdAt: _parseDateTime(item['created_at']),
      updatedAt: _parseDateTime(item['updated_at']),
      resolvedAt: _parseDateTime(item['resolved_at']),
    );
  }

  Future<Map<String, dynamic>> _getOptionalMap(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final response = await _apiService.get(
        path,
        queryParameters: queryParameters,
      );

      return _asMap(response.data) ?? <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
  }

  Map<String, dynamic>? _asMap(dynamic value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }
    return null;
  }

  List<dynamic> _asList(dynamic value) {
    if (value is List) {
      return value;
    }
    return const <dynamic>[];
  }

  int _parseInt(dynamic value) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse('${value ?? 0}') ?? 0;
  }

  double? _parseDouble(dynamic value) {
    if (value is double) {
      return value;
    }
    if (value is int) {
      return value.toDouble();
    }
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse('${value ?? ''}');
  }

  bool _parseBool(dynamic value) {
    if (value is bool) {
      return value;
    }
    if (value is int) {
      return value == 1;
    }
    final normalized = '${value ?? ''}'.trim().toLowerCase();
    return normalized == 'true' || normalized == '1';
  }

  DateTime? _parseDateTime(dynamic value) {
    if (value == null) {
      return null;
    }
    return DateTime.tryParse(value.toString());
  }
}
