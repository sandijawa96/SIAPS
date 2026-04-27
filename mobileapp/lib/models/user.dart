class User {
  final int id;
  final String username;
  final String email;
  final String? namaLengkap;
  final String? nisn;
  final String? nis;
  final String? nip;
  final String? nik;
  final String? fotoProfil;
  final String? statusKepegawaian;
  final String? nuptk;
  final bool isActive;
  final String? deviceId;
  final String? deviceName;
  final DateTime? deviceBoundAt;
  final bool deviceLocked;
  final DateTime? lastDeviceActivity;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final List<Role> roles;
  final List<String> permissions;
  final String? role;
  final String? kelasNama;
  final int? idKelas;
  final String? waliKelasNama;
  final int? waliKelasId;
  final String? waliKelasNip;
  final List<String> attendanceMethods;
  final String? attendanceMethodsLabel;
  final String? attendanceLocationLabel;
  final bool hasActiveFaceTemplate;
  final DateTime? faceTemplateEnrolledAt;

  User({
    required this.id,
    required this.username,
    required this.email,
    this.namaLengkap,
    this.nisn,
    this.nis,
    this.nip,
    this.nik,
    this.fotoProfil,
    this.statusKepegawaian,
    this.nuptk,
    required this.isActive,
    this.deviceId,
    this.deviceName,
    this.deviceBoundAt,
    required this.deviceLocked,
    this.lastDeviceActivity,
    this.createdAt,
    this.updatedAt,
    required this.roles,
    required this.permissions,
    this.role,
    this.kelasNama,
    this.idKelas,
    this.waliKelasNama,
    this.waliKelasId,
    this.waliKelasNip,
    required this.attendanceMethods,
    this.attendanceMethodsLabel,
    this.attendanceLocationLabel,
    required this.hasActiveFaceTemplate,
    this.faceTemplateEnrolledAt,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    try {
      // Parse roles first
      List<Role> userRoles = [];
      if (json['roles'] != null && json['roles'] is List) {
        userRoles =
            (json['roles'] as List).map((role) => Role.fromJson(role)).toList();
      }

      // Parse permissions - handle both direct permissions and permissions from roles
      List<String> userPermissions = [];

      // Direct permissions (if provided as array of strings)
      if (json['permissions'] != null) {
        if (json['permissions'] is List) {
          try {
            userPermissions = List<String>.from(json['permissions']);
          } catch (e) {
            print('🔍 Error parsing direct permissions: $e');
          }
        }
      }

      // For now, just use empty permissions if not provided directly
      // The backend should provide permissions directly in the user object
      print('🔍 Final permissions count: ${userPermissions.length}');

      return User(
        id: json['id'] ?? 0,
        username: json['username'] ?? '',
        email: json['email'] ?? '',
        namaLengkap: json['nama_lengkap'],
        nisn: json['nisn'],
        nis: json['nis'],
        nip: json['nip'],
        nik: json['nik'],
        fotoProfil: json['foto_profil_url'] ?? json['foto_profil'],
        statusKepegawaian: json['status_kepegawaian'],
        nuptk: json['nuptk'],
        isActive: _parseBool(json['is_active']) ?? true,
        deviceId: json['device_id'],
        deviceName: json['device_name'],
        deviceBoundAt: json['device_bound_at'] != null
            ? DateTime.tryParse(json['device_bound_at'].toString())
            : null,
        deviceLocked: _parseBool(json['device_locked']) ?? false,
        lastDeviceActivity: json['last_device_activity'] != null
            ? DateTime.tryParse(json['last_device_activity'].toString())
            : null,
        createdAt: json['created_at'] != null
            ? DateTime.tryParse(json['created_at'])
            : null,
        updatedAt: json['updated_at'] != null
            ? DateTime.tryParse(json['updated_at'])
            : null,
        roles: userRoles,
        permissions: userPermissions,
        role: json['role'] ??
            (userRoles.isNotEmpty ? userRoles.first.name : null),
        kelasNama: json['kelas_nama'] ?? json['kelasNama'],
        idKelas: json['kelas_id']
            as int?, // Changed from 'id_kelas' to 'kelas_id' for consistency
        waliKelasNama: json['wali_kelas_nama']?.toString(),
        waliKelasId: json['wali_kelas_id'] is int
            ? json['wali_kelas_id'] as int
            : int.tryParse('${json['wali_kelas_id'] ?? ''}'),
        waliKelasNip: json['wali_kelas_nip']?.toString(),
        attendanceMethods: _parseStringList(json['attendance_methods']),
        attendanceMethodsLabel: json['attendance_methods_label']?.toString(),
        attendanceLocationLabel: json['attendance_location_label']?.toString(),
        hasActiveFaceTemplate: _parseBool(json['has_active_face_template']) ?? false,
        faceTemplateEnrolledAt: json['face_template_enrolled_at'] != null
            ? DateTime.tryParse(json['face_template_enrolled_at'].toString())
            : null,
      );
    } catch (e) {
      print('Error parsing User from JSON: $e');
      print('JSON data: $json');
      rethrow;
    }
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'email': email,
      'nama_lengkap': namaLengkap,
      'nisn': nisn,
      'nis': nis,
      'nip': nip,
      'nik': nik,
      'foto_profil': fotoProfil,
      'status_kepegawaian': statusKepegawaian,
      'nuptk': nuptk,
      'is_active': isActive,
      'device_id': deviceId,
      'device_name': deviceName,
      'device_bound_at': deviceBoundAt?.toIso8601String(),
      'device_locked': deviceLocked,
      'last_device_activity': lastDeviceActivity?.toIso8601String(),
      'created_at': createdAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
      'roles': roles.map((role) => role.toJson()).toList(),
      'permissions': permissions,
      'role': role,
      'kelas_nama': kelasNama,
      'kelas_id':
          idKelas, // Changed from 'id_kelas' to 'kelas_id' for consistency
      'wali_kelas_nama': waliKelasNama,
      'wali_kelas_id': waliKelasId,
      'wali_kelas_nip': waliKelasNip,
      'attendance_methods': attendanceMethods,
      'attendance_methods_label': attendanceMethodsLabel,
      'attendance_location_label': attendanceLocationLabel,
      'has_active_face_template': hasActiveFaceTemplate,
      'face_template_enrolled_at': faceTemplateEnrolledAt?.toIso8601String(),
    };
  }

  User copyWith({
    int? id,
    String? username,
    String? email,
    String? namaLengkap,
    String? nisn,
    String? nis,
    String? nip,
    String? nik,
    String? fotoProfil,
    String? statusKepegawaian,
    String? nuptk,
    bool? isActive,
    String? deviceId,
    String? deviceName,
    DateTime? deviceBoundAt,
    bool? deviceLocked,
    DateTime? lastDeviceActivity,
    DateTime? createdAt,
    DateTime? updatedAt,
    List<Role>? roles,
    List<String>? permissions,
    String? role,
    String? kelasNama,
    int? idKelas,
    String? waliKelasNama,
    int? waliKelasId,
    String? waliKelasNip,
    List<String>? attendanceMethods,
    String? attendanceMethodsLabel,
    String? attendanceLocationLabel,
    bool? hasActiveFaceTemplate,
    DateTime? faceTemplateEnrolledAt,
  }) {
    return User(
      id: id ?? this.id,
      username: username ?? this.username,
      email: email ?? this.email,
      namaLengkap: namaLengkap ?? this.namaLengkap,
      nisn: nisn ?? this.nisn,
      nis: nis ?? this.nis,
      nip: nip ?? this.nip,
      nik: nik ?? this.nik,
      fotoProfil: fotoProfil ?? this.fotoProfil,
      statusKepegawaian: statusKepegawaian ?? this.statusKepegawaian,
      nuptk: nuptk ?? this.nuptk,
      isActive: isActive ?? this.isActive,
      deviceId: deviceId ?? this.deviceId,
      deviceName: deviceName ?? this.deviceName,
      deviceBoundAt: deviceBoundAt ?? this.deviceBoundAt,
      deviceLocked: deviceLocked ?? this.deviceLocked,
      lastDeviceActivity: lastDeviceActivity ?? this.lastDeviceActivity,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      roles: roles ?? this.roles,
      permissions: permissions ?? this.permissions,
      role: role ?? this.role,
      kelasNama: kelasNama ?? this.kelasNama,
      idKelas: idKelas ?? this.idKelas,
      waliKelasNama: waliKelasNama ?? this.waliKelasNama,
      waliKelasId: waliKelasId ?? this.waliKelasId,
      waliKelasNip: waliKelasNip ?? this.waliKelasNip,
      attendanceMethods: attendanceMethods ?? this.attendanceMethods,
      attendanceMethodsLabel:
          attendanceMethodsLabel ?? this.attendanceMethodsLabel,
      attendanceLocationLabel:
          attendanceLocationLabel ?? this.attendanceLocationLabel,
      hasActiveFaceTemplate: hasActiveFaceTemplate ?? this.hasActiveFaceTemplate,
      faceTemplateEnrolledAt: faceTemplateEnrolledAt ?? this.faceTemplateEnrolledAt,
    );
  }

  bool hasPermission(String permission) {
    return permissions.contains(permission);
  }

  bool hasRole(String roleName) {
    final normalizedTarget = _normalizeRoleName(roleName);
    return roles.any((role) => _normalizeRoleName(role.name) == normalizedTarget);
  }

  bool get isSuperAdmin {
    return hasRole('Super_Admin');
  }

  bool get isAdmin {
    return hasRole('Admin');
  }

  bool get isSiswa {
    return hasRole('siswa') || hasRole('Siswa');
  }

  bool get isWaliKelas {
    return hasRole('Wali Kelas') || hasRole('Wali_Kelas');
  }

  bool get isWakasekKesiswaan {
    return hasRole('Wakasek_Kesiswaan') || hasRole('Wakasek Kesiswaan');
  }

  bool get isWakasekKurikulum {
    return hasRole('Wakasek_Kurikulum') || hasRole('Wakasek Kurikulum');
  }

  bool get isWakasekHumas {
    return hasRole('Wakasek_Humas') || hasRole('Wakasek Humas');
  }

  bool get isWakasekSarpras {
    return hasRole('Wakasek_Sarpras') || hasRole('Wakasek Sarpras');
  }

  bool get isKepalaSekolah {
    return hasRole('Kepala_Sekolah') || hasRole('Kepala Sekolah');
  }

  bool get isGuru {
    return hasRole('Guru');
  }

  bool get isGuruBk {
    return hasRole('Guru_BK') || hasRole('Guru BK');
  }

  bool get isStaffTu {
    return hasRole('Staff_TU') || hasRole('Staff TU');
  }

  bool get isPegawai {
    return !isSiswa;
  }

  bool get canApproveStudentLeave {
    return isSuperAdmin || isAdmin || isWakasekKesiswaan || isWaliKelas;
  }

  bool get canManageManualAttendance {
    return isSuperAdmin || hasPermission('manual_attendance');
  }

  bool get canOverrideManualAttendanceBackdate {
    return isSuperAdmin || hasPermission('manual_attendance_backdate_override');
  }

  bool get canOpenAttendanceMonitoringMenu {
    return isSuperAdmin || isWaliKelas || isWakasekKesiswaan;
  }

  bool get canOpenWaliClassMenu {
    return canOpenAttendanceMonitoringMenu;
  }

  String get attendanceMonitoringMenuTitle {
    if (isSuperAdmin || (isWakasekKesiswaan && !isWaliKelas)) {
      return 'Monitoring Kelas';
    }

    return 'Kelas Saya';
  }

  String get attendanceMonitoringMenuSubtitle {
    if (isSuperAdmin || (isWakasekKesiswaan && !isWaliKelas)) {
      return 'Ringkasan kehadiran siswa hari ini per kelas.';
    }

    return 'Ringkasan kelas binaan beserta kehadiran hari ini.';
  }

  bool get canViewScheduleOnMobile {
    return isSiswa || isGuru || isWaliKelas;
  }

  String get displayName {
    return namaLengkap ?? username;
  }

  String get identifier {
    if (isSiswa) {
      return nis ?? nisn ?? username;
    }
    return nip ?? email;
  }

  static bool? _parseBool(dynamic value) {
    if (value == null) return null;
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      return value.toLowerCase() == 'true' || value == '1';
    }
    return null;
  }

  static List<String> _parseStringList(dynamic value) {
    if (value is! List) {
      return const <String>[];
    }

    return value
        .map((item) => item?.toString().trim() ?? '')
        .where((item) => item.isNotEmpty)
        .toList();
  }

  static String _normalizeRoleName(String? value) {
    return (value ?? '')
        .trim()
        .toLowerCase()
        .replaceAll('_', ' ')
        .replaceAll(RegExp(r'\s+'), ' ')
        .replaceAll(RegExp(r'\s+(web|api)$'), '')
        .trim();
  }
}

class Role {
  final int id;
  final String name;
  final String? displayName;
  final String? description;
  final int? level;
  final bool isActive;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  Role({
    required this.id,
    required this.name,
    this.displayName,
    this.description,
    this.level,
    required this.isActive,
    this.createdAt,
    this.updatedAt,
  });

  factory Role.fromJson(Map<String, dynamic> json) {
    return Role(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      displayName: json['display_name'],
      description: json['description'],
      level: json['level'],
      isActive: _parseBool(json['is_active']) ?? true,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'])
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.tryParse(json['updated_at'])
          : null,
    );
  }

  static bool? _parseBool(dynamic value) {
    if (value == null) return null;
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      return value.toLowerCase() == 'true' || value == '1';
    }
    return null;
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'display_name': displayName,
      'description': description,
      'level': level,
      'is_active': isActive,
      'created_at': createdAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
    };
  }
}
