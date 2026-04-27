class DisciplineThresholdMetric {
  final String ruleKey;
  final String label;
  final String periodType;
  final String periodKey;
  final String periodLabel;
  final String metricUnit;
  final String mode;
  final bool alertable;
  final int currentValue;
  final int limit;
  final bool exceeded;
  final bool notifyWaliKelas;
  final bool notifyKesiswaan;
  final String? startDate;
  final String? endDate;

  const DisciplineThresholdMetric({
    this.ruleKey = '',
    this.label = '',
    this.periodType = '',
    this.periodKey = '',
    this.periodLabel = '',
    this.metricUnit = 'menit',
    this.mode = 'monitor_only',
    this.alertable = false,
    this.currentValue = 0,
    this.limit = 0,
    this.exceeded = false,
    this.notifyWaliKelas = false,
    this.notifyKesiswaan = false,
    this.startDate,
    this.endDate,
  });

  static const DisciplineThresholdMetric empty = DisciplineThresholdMetric();

  factory DisciplineThresholdMetric.fromJson(
    Map<String, dynamic>? json, {
    String valueKey = 'minutes',
  }) {
    if (json == null) {
      return empty;
    }

    int parseInt(dynamic value) {
      if (value is int) return value;
      if (value is double) return value.toInt();
      if (value is String) return int.tryParse(value) ?? 0;
      return 0;
    }

    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) {
        final normalized = value.trim().toLowerCase();
        return normalized == 'true' || normalized == '1';
      }
      return false;
    }

    return DisciplineThresholdMetric(
      ruleKey: json['rule_key']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      periodType: json['period_type']?.toString() ?? '',
      periodKey: json['period_key']?.toString() ?? '',
      periodLabel:
          json['period_label']?.toString() ??
          json['semester_label']?.toString() ??
          '',
      metricUnit: json['metric_unit']?.toString() ??
          (valueKey == 'days' ? 'hari' : 'menit'),
      mode: json['mode']?.toString() ?? 'monitor_only',
      alertable: parseBool(json['alertable']),
      currentValue: parseInt(json[valueKey]),
      limit: parseInt(json['limit']),
      exceeded: parseBool(json['exceeded']),
      notifyWaliKelas: parseBool(json['notify_wali_kelas']),
      notifyKesiswaan: parseBool(json['notify_kesiswaan']),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
    );
  }

  Map<String, dynamic> toJson({String valueKey = 'minutes'}) {
    return {
      'rule_key': ruleKey,
      'label': label,
      'period_type': periodType,
      'period_key': periodKey,
      'period_label': periodLabel,
      'metric_unit': metricUnit,
      'mode': mode,
      'alertable': alertable,
      valueKey: currentValue,
      'limit': limit,
      'exceeded': exceeded,
      'notify_wali_kelas': notifyWaliKelas,
      'notify_kesiswaan': notifyKesiswaan,
      'start_date': startDate,
      'end_date': endDate,
    };
  }
}

class DisciplineThresholdSnapshot {
  final String mode;
  final DisciplineThresholdMetric monthlyLate;
  final DisciplineThresholdMetric semesterTotalViolation;
  final DisciplineThresholdMetric semesterAlpha;
  final bool attentionNeeded;

  const DisciplineThresholdSnapshot({
    this.mode = 'none',
    this.monthlyLate = DisciplineThresholdMetric.empty,
    this.semesterTotalViolation = DisciplineThresholdMetric.empty,
    this.semesterAlpha = DisciplineThresholdMetric.empty,
    this.attentionNeeded = false,
  });

  static const DisciplineThresholdSnapshot empty =
      DisciplineThresholdSnapshot();

  factory DisciplineThresholdSnapshot.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return empty;
    }

    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) {
        final normalized = value.trim().toLowerCase();
        return normalized == 'true' || normalized == '1';
      }
      return false;
    }

    return DisciplineThresholdSnapshot(
      mode: json['mode']?.toString() ?? 'none',
      monthlyLate: DisciplineThresholdMetric.fromJson(
        json['monthly_late'] is Map<String, dynamic>
            ? json['monthly_late'] as Map<String, dynamic>
            : (json['monthly_late'] is Map
                ? Map<String, dynamic>.from(json['monthly_late'] as Map)
                : null),
      ),
      semesterTotalViolation: DisciplineThresholdMetric.fromJson(
        json['semester_total_violation'] is Map<String, dynamic>
            ? json['semester_total_violation'] as Map<String, dynamic>
            : (json['semester_total_violation'] is Map
                ? Map<String, dynamic>.from(
                    json['semester_total_violation'] as Map,
                  )
                : null),
      ),
      semesterAlpha: DisciplineThresholdMetric.fromJson(
        json['semester_alpha'] is Map<String, dynamic>
            ? json['semester_alpha'] as Map<String, dynamic>
            : (json['semester_alpha'] is Map
                ? Map<String, dynamic>.from(json['semester_alpha'] as Map)
                : null),
        valueKey: 'days',
      ),
      attentionNeeded: parseBool(json['attention_needed']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'mode': mode,
      'monthly_late': monthlyLate.toJson(),
      'semester_total_violation': semesterTotalViolation.toJson(),
      'semester_alpha': semesterAlpha.toJson(valueKey: 'days'),
      'attention_needed': attentionNeeded,
    };
  }
}

class MonthlyRecapData {
  final int masuk; // Hari
  final int cuti; // Hari
  final int alpa; // Hari
  final int dinas; // Hari
  final int izin; // Hari
  final int sakit; // Hari
  final int terlambatHari; // Hari
  final int terlambatMenit; // Menit
  final int terlambat; // Backward compatibility alias for terlambatMenit
  final int tapHari; // Hari
  final int tapMenit; // Menit (lupa absen pulang)
  final int tap; // Backward compatibility alias for tapMenit
  final int totalTK; // Total tanpa keterangan/alpa Menit
  final int alpaHari;
  final int alpaMenit;
  final int pelanggaranMenit;
  final double persentasePelanggaran;
  final int batasPelanggaranMenit;
  final double batasPelanggaranPersen;
  final bool melewatiBatasPelanggaran;
  final DisciplineThresholdSnapshot disciplineThresholds;
  final int menitKerjaPerHari;
  final int totalMenitKerjaBulan;
  final int workingDays;
  final int schoolDaysInMonth;
  final double attendanceRate;

  const MonthlyRecapData({
    this.masuk = 0,
    this.cuti = 0,
    this.alpa = 0,
    this.dinas = 0,
    this.izin = 0,
    this.sakit = 0,
    this.terlambatHari = 0,
    this.terlambatMenit = 0,
    this.terlambat = 0,
    this.tapHari = 0,
    this.tapMenit = 0,
    this.tap = 0,
    this.totalTK = 0,
    this.alpaHari = 0,
    this.alpaMenit = 0,
    this.pelanggaranMenit = 0,
    this.persentasePelanggaran = 0,
    this.batasPelanggaranMenit = 0,
    this.batasPelanggaranPersen = 0,
    this.melewatiBatasPelanggaran = false,
    this.disciplineThresholds = DisciplineThresholdSnapshot.empty,
    this.menitKerjaPerHari = 0,
    this.totalMenitKerjaBulan = 0,
    this.workingDays = 0,
    this.schoolDaysInMonth = 0,
    this.attendanceRate = 0,
  });

  /// Create from JSON data received from backend
  factory MonthlyRecapData.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value is int) return value;
      if (value is double) return value.toInt();
      if (value is String) return int.tryParse(value) ?? 0;
      return 0;
    }

    double parseDouble(dynamic value) {
      if (value is double) return value;
      if (value is int) return value.toDouble();
      if (value is String) return double.tryParse(value) ?? 0.0;
      return 0.0;
    }

    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) {
        final normalized = value.trim().toLowerCase();
        return normalized == 'true' || normalized == '1';
      }
      return false;
    }

    final terlambatHari = parseInt(json['terlambat_hari'] ?? json['late_days']);
    final terlambatMenit = parseInt(
      json['terlambat_menit'] ?? json['terlambat'],
    );
    final tapHari = parseInt(
      json['tap_hari'] ?? json['tap_days'] ?? json['total_tap_hari'],
    );
    final tapMenit = parseInt(
      json['tap_menit'] ?? json['tap'] ?? json['total_tap_menit'],
    );

    return MonthlyRecapData(
      masuk: parseInt(json['masuk']),
      cuti: parseInt(json['cuti']),
      alpa: parseInt(json['alpa']),
      dinas: parseInt(json['dinas']),
      izin: parseInt(json['izin']),
      sakit: parseInt(json['sakit']),
      terlambatHari: terlambatHari,
      terlambatMenit: terlambatMenit,
      terlambat: terlambatMenit,
      tapHari: tapHari,
      tapMenit: tapMenit,
      tap: tapMenit,
      totalTK: parseInt(json['totalTK']),
      alpaHari: parseInt(json['alpa_hari'] ?? json['alpa']),
      alpaMenit: parseInt(json['alpa_menit']),
      pelanggaranMenit: parseInt(json['pelanggaran_menit'] ?? json['totalTK']),
      persentasePelanggaran: parseDouble(json['persentase_pelanggaran']),
      batasPelanggaranMenit: parseInt(json['batas_pelanggaran_menit']),
      batasPelanggaranPersen: parseDouble(json['batas_pelanggaran_persen']),
      melewatiBatasPelanggaran: parseBool(json['melewati_batas_pelanggaran']),
      disciplineThresholds: DisciplineThresholdSnapshot.fromJson(
        json['discipline_thresholds'] is Map<String, dynamic>
            ? json['discipline_thresholds'] as Map<String, dynamic>
            : (json['discipline_thresholds'] is Map
                ? Map<String, dynamic>.from(json['discipline_thresholds'] as Map)
                : null),
      ),
      menitKerjaPerHari: parseInt(
        json['menit_sekolah_per_hari'] ?? json['menit_kerja_per_hari'],
      ),
      totalMenitKerjaBulan: parseInt(
        json['total_menit_sekolah_bulan'] ??
            json['total_menit_sekolah'] ??
            json['total_menit_kerja_bulan'],
      ),
      workingDays: parseInt(json['school_days'] ?? json['working_days']),
      schoolDaysInMonth: parseInt(
        json['school_days_in_month'] ??
            json['school_days'] ??
            json['working_days'],
      ),
      attendanceRate: parseDouble(json['attendance_rate']),
    );
  }

  /// Convert to JSON for sending to backend
  Map<String, dynamic> toJson() {
    return {
      'masuk': masuk,
      'cuti': cuti,
      'alpa': alpa,
      'dinas': dinas,
      'izin': izin,
      'sakit': sakit,
      'terlambat_hari': terlambatHari,
      'terlambat_menit': terlambatMenit,
      'terlambat': terlambat,
      'tap_hari': tapHari,
      'tap_menit': tapMenit,
      'tap': tap,
      'totalTK': totalTK,
      'alpa_hari': alpaHari,
      'alpa_menit': alpaMenit,
      'pelanggaran_menit': pelanggaranMenit,
      'persentase_pelanggaran': persentasePelanggaran,
      'batas_pelanggaran_menit': batasPelanggaranMenit,
      'batas_pelanggaran_persen': batasPelanggaranPersen,
      'melewati_batas_pelanggaran': melewatiBatasPelanggaran,
      'discipline_thresholds': disciplineThresholds.toJson(),
      'menit_sekolah_per_hari': menitKerjaPerHari,
      'menit_kerja_per_hari': menitKerjaPerHari,
      'total_menit_sekolah_bulan': totalMenitKerjaBulan,
      'total_menit_kerja_bulan': totalMenitKerjaBulan,
      'working_days': workingDays,
      'school_days': workingDays,
      'school_days_in_month': schoolDaysInMonth,
      'attendance_rate': attendanceRate,
    };
  }

  int get totalExcusedDays => izin + sakit + cuti + dinas;

  int get recordedDays => masuk + alpaHari + totalExcusedDays;

  bool get isEmpty =>
      masuk == 0 &&
      cuti == 0 &&
      alpa == 0 &&
      dinas == 0 &&
      izin == 0 &&
      sakit == 0 &&
      terlambatMenit == 0 &&
      tapMenit == 0 &&
      totalTK == 0 &&
      workingDays == 0;

  /// Create empty data (for loading states)
  static const MonthlyRecapData empty = MonthlyRecapData();

  /// Backward compatibility - returns empty data
  /// Use MonthlyRecapService to get real data from backend
  @Deprecated('Use MonthlyRecapService to fetch live monthly recap data.')
  static MonthlyRecapData getSampleData() {
    return empty;
  }

  /// Backward compatibility - returns empty data
  /// Use MonthlyRecapService.getCurrentMonthRecap() instead
  @Deprecated('Use MonthlyRecapService.getCurrentMonthRecap() instead.')
  static MonthlyRecapData getCurrentMonthData() {
    return empty;
  }

  /// Backward compatibility - returns empty data
  /// Use MonthlyRecapService.getPreviousMonthRecap() instead
  @Deprecated('Use MonthlyRecapService.getPreviousMonthRecap() instead.')
  static MonthlyRecapData getPreviousMonthData() {
    return empty;
  }
}
