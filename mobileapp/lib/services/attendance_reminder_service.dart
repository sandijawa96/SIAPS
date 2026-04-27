import 'dart:convert';
import 'dart:io' show Platform;
import 'dart:ui' show Color;

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

import '../models/user.dart';
import 'attendance_service.dart';

class AttendanceReminderPreferences {
  final bool checkInEnabled;
  final bool checkOutEnabled;
  final int minutesBefore;

  const AttendanceReminderPreferences({
    required this.checkInEnabled,
    required this.checkOutEnabled,
    required this.minutesBefore,
  });

  AttendanceReminderPreferences copyWith({
    bool? checkInEnabled,
    bool? checkOutEnabled,
    int? minutesBefore,
  }) {
    return AttendanceReminderPreferences(
      checkInEnabled: checkInEnabled ?? this.checkInEnabled,
      checkOutEnabled: checkOutEnabled ?? this.checkOutEnabled,
      minutesBefore: minutesBefore ?? this.minutesBefore,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'check_in_enabled': checkInEnabled,
      'check_out_enabled': checkOutEnabled,
      'minutes_before': minutesBefore,
    };
  }

  factory AttendanceReminderPreferences.fromJson(Map<String, dynamic> json) {
    bool parseBool(dynamic value, bool fallback) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) {
        final normalized = value.toLowerCase();
        return normalized == 'true' || normalized == '1';
      }
      return fallback;
    }

    int parseInt(dynamic value, int fallback) {
      if (value is int) return value;
      if (value is double) return value.round();
      if (value is String) return int.tryParse(value) ?? fallback;
      return fallback;
    }

    return AttendanceReminderPreferences(
      checkInEnabled: parseBool(json['check_in_enabled'], true),
      checkOutEnabled: parseBool(json['check_out_enabled'], true),
      minutesBefore: parseInt(json['minutes_before'], 10),
    );
  }

  static const AttendanceReminderPreferences defaults =
      AttendanceReminderPreferences(
    checkInEnabled: true,
    checkOutEnabled: true,
    minutesBefore: 10,
  );
}

class AttendanceReminderService {
  AttendanceReminderService._();
  static final AttendanceReminderService _instance =
      AttendanceReminderService._();
  factory AttendanceReminderService() => _instance;

  static const String _storageKey = 'attendance_reminder_preferences';
  static const String _androidChannelId = 'siaps_notifications';
  static const String _androidChannelName = 'SIAPS Notifications';
  static const String _androidChannelDescription =
      'Notifikasi utama aplikasi SIAPS';
  static const String _androidNotificationIcon = 'ic_notification';
  static const String _androidLargeNotificationIcon = 'ic_notification';
  static const Color _androidNotificationColor = Color(0xFF64B5F6);

  static const int _checkInBaseId = 61000;
  static const int _checkOutBaseId = 62000;

  static const Map<String, int> _weekdayMap = {
    'monday': DateTime.monday,
    'senin': DateTime.monday,
    'tuesday': DateTime.tuesday,
    'selasa': DateTime.tuesday,
    'wednesday': DateTime.wednesday,
    'rabu': DateTime.wednesday,
    'thursday': DateTime.thursday,
    'kamis': DateTime.thursday,
    'friday': DateTime.friday,
    'jumat': DateTime.friday,
    "jum'at": DateTime.friday,
    'saturday': DateTime.saturday,
    'sabtu': DateTime.saturday,
    'sunday': DateTime.sunday,
    'minggu': DateTime.sunday,
  };

  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  final FlutterLocalNotificationsPlugin _notifications =
      FlutterLocalNotificationsPlugin();
  final AttendanceService _attendanceService = AttendanceService();
  bool _initialized = false;
  bool _notificationsAvailable = false;

  Future<bool> initialize() async {
    if (_initialized) {
      return _notificationsAvailable;
    }

    if (!Platform.isAndroid) {
      _initialized = true;
      _notificationsAvailable = false;
      return false;
    }

    try {
      tz.initializeTimeZones();
      tz.setLocalLocation(tz.getLocation('Asia/Jakarta'));
    } catch (_) {
      // Keep existing local location if timezone cannot be resolved.
    }

    try {
      const androidInit =
          AndroidInitializationSettings(_androidNotificationIcon);
      const initSettings = InitializationSettings(android: androidInit);
      await _notifications.initialize(initSettings);

      const channel = AndroidNotificationChannel(
        _androidChannelId,
        _androidChannelName,
        description: _androidChannelDescription,
        importance: Importance.max,
      );

      final androidNotifications =
          _notifications.resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>();
      await androidNotifications?.createNotificationChannel(channel);
      await androidNotifications?.requestNotificationsPermission();

      _notificationsAvailable = true;
    } catch (e) {
      debugPrint(
        '[AttendanceReminderService] Notification initialization failed: $e',
      );
      _notificationsAvailable = false;
    }

    _initialized = true;
    return _notificationsAvailable;
  }

  Future<AttendanceReminderPreferences> getPreferences() async {
    final raw = await _storage.read(key: _storageKey);
    if (raw == null || raw.trim().isEmpty) {
      return AttendanceReminderPreferences.defaults;
    }

    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map<String, dynamic>) {
        return AttendanceReminderPreferences.fromJson(decoded);
      }
      if (decoded is Map) {
        return AttendanceReminderPreferences.fromJson(
            Map<String, dynamic>.from(decoded));
      }
    } catch (e) {
      debugPrint('[AttendanceReminderService] Failed to parse preferences: $e');
    }

    return AttendanceReminderPreferences.defaults;
  }

  Future<AttendanceReminderPreferences> savePreferences({
    bool? checkInEnabled,
    bool? checkOutEnabled,
  }) async {
    final current = await getPreferences();
    final next = current.copyWith(
      checkInEnabled: checkInEnabled,
      checkOutEnabled: checkOutEnabled,
    );

    await _storage.write(key: _storageKey, value: jsonEncode(next.toJson()));
    return next;
  }

  Future<void> applySchedulesForUser(User? user) async {
    final notificationsReady = await initialize();
    if (!notificationsReady) {
      debugPrint(
        '[AttendanceReminderService] Notifications unavailable, skipping schedule apply.',
      );
      return;
    }

    await cancelAllAttendanceReminders();

    if (!Platform.isAndroid || user == null || !user.isSiswa) {
      return;
    }

    final prefs = await getPreferences();
    if (!prefs.checkInEnabled && !prefs.checkOutEnabled) {
      return;
    }

    final workingHoursResponse = await _attendanceService.getWorkingHours();
    if (!workingHoursResponse.success || workingHoursResponse.data == null) {
      debugPrint(
        '[AttendanceReminderService] Unable to schedule reminders: ${workingHoursResponse.message}',
      );
      return;
    }

    final workingHours = workingHoursResponse.data!;
    final weekdays = _resolveWorkingWeekdays(workingHours.hariKerja);
    if (weekdays.isEmpty) {
      return;
    }

    if (prefs.checkInEnabled) {
      final reminder =
          _subtractMinutes(workingHours.jamMasuk, prefs.minutesBefore);
      for (final weekday in weekdays) {
        final targetWeekday =
            reminder.shiftToPreviousDay ? _previousWeekday(weekday) : weekday;
        try {
          await _scheduleWeeklyReminder(
            id: _checkInBaseId + weekday,
            weekday: targetWeekday,
            hour: reminder.hour,
            minute: reminder.minute,
            title: 'Pengingat Absen Masuk',
            body:
                '${prefs.minutesBefore} menit lagi waktu masuk (${workingHours.jamMasuk}). Silakan siapkan absensi.',
            payload:
                jsonEncode({'type': 'attendance_reminder', 'kind': 'check_in'}),
          );
        } catch (e) {
          debugPrint(
            '[AttendanceReminderService] Failed to schedule check-in reminder for weekday $weekday: $e',
          );
        }
      }
    }

    if (prefs.checkOutEnabled) {
      final reminder =
          _subtractMinutes(workingHours.jamPulang, prefs.minutesBefore);
      for (final weekday in weekdays) {
        final targetWeekday =
            reminder.shiftToPreviousDay ? _previousWeekday(weekday) : weekday;
        try {
          await _scheduleWeeklyReminder(
            id: _checkOutBaseId + weekday,
            weekday: targetWeekday,
            hour: reminder.hour,
            minute: reminder.minute,
            title: 'Pengingat Absen Pulang',
            body:
                '${prefs.minutesBefore} menit lagi waktu pulang (${workingHours.jamPulang}). Jangan lupa absen pulang.',
            payload:
                jsonEncode({'type': 'attendance_reminder', 'kind': 'check_out'}),
          );
        } catch (e) {
          debugPrint(
            '[AttendanceReminderService] Failed to schedule check-out reminder for weekday $weekday: $e',
          );
        }
      }
    }
  }

  Future<void> cancelAllAttendanceReminders() async {
    final notificationsReady = await initialize();
    if (!notificationsReady) {
      return;
    }

    for (var weekday = DateTime.monday; weekday <= DateTime.sunday; weekday++) {
      try {
        await _notifications.cancel(_checkInBaseId + weekday);
        await _notifications.cancel(_checkOutBaseId + weekday);
      } catch (e) {
        debugPrint(
          '[AttendanceReminderService] Failed to cancel reminder for weekday $weekday: $e',
        );
      }
    }
  }

  Future<void> _scheduleWeeklyReminder({
    required int id,
    required int weekday,
    required int hour,
    required int minute,
    required String title,
    required String body,
    required String payload,
  }) async {
    final scheduled = _nextInstanceOfWeekdayAndTime(
        weekday: weekday, hour: hour, minute: minute);
    await _notifications.zonedSchedule(
      id,
      title,
      body,
      scheduled,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          _androidChannelId,
          _androidChannelName,
          channelDescription: _androidChannelDescription,
          icon: _androidNotificationIcon,
          largeIcon:
              DrawableResourceAndroidBitmap(_androidLargeNotificationIcon),
          color: _androidNotificationColor,
          importance: Importance.max,
          priority: Priority.high,
          visibility: NotificationVisibility.public,
          playSound: true,
        ),
      ),
      androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
      uiLocalNotificationDateInterpretation:
          UILocalNotificationDateInterpretation.absoluteTime,
      matchDateTimeComponents: DateTimeComponents.dayOfWeekAndTime,
      payload: payload,
    );
  }

  tz.TZDateTime _nextInstanceOfWeekdayAndTime({
    required int weekday,
    required int hour,
    required int minute,
  }) {
    final now = tz.TZDateTime.now(tz.local);
    final candidateToday = tz.TZDateTime(
      tz.local,
      now.year,
      now.month,
      now.day,
      hour,
      minute,
    );

    var delta = (weekday - now.weekday + 7) % 7;
    if (delta == 0 && candidateToday.isBefore(now)) {
      delta = 7;
    }

    final scheduledDate = now.add(Duration(days: delta));
    return tz.TZDateTime(
      tz.local,
      scheduledDate.year,
      scheduledDate.month,
      scheduledDate.day,
      hour,
      minute,
    );
  }

  List<int> _resolveWorkingWeekdays(List<String> days) {
    if (days.isEmpty) {
      return const <int>[
        DateTime.monday,
        DateTime.tuesday,
        DateTime.wednesday,
        DateTime.thursday,
        DateTime.friday,
      ];
    }

    final result = <int>{};
    for (final day in days) {
      final key = day.trim().toLowerCase();
      final value = _weekdayMap[key];
      if (value != null) {
        result.add(value);
      }
    }

    return result.isNotEmpty
        ? (result.toList()..sort())
        : const <int>[
            DateTime.monday,
            DateTime.tuesday,
            DateTime.wednesday,
            DateTime.thursday,
            DateTime.friday,
          ];
  }

  _ReminderTimeSlot _subtractMinutes(String hhmm, int minutes) {
    final parsed = _parseHourMinute(hhmm);
    var total = parsed.$1 * 60 + parsed.$2 - minutes;
    var shiftToPreviousDay = false;
    if (total < 0) {
      total += 24 * 60;
      shiftToPreviousDay = true;
    }

    return _ReminderTimeSlot(
      hour: total ~/ 60,
      minute: total % 60,
      shiftToPreviousDay: shiftToPreviousDay,
    );
  }

  (int, int) _parseHourMinute(String value) {
    final parts = value.split(':');
    if (parts.length < 2) {
      return (7, 0);
    }

    final hour = int.tryParse(parts[0]) ?? 7;
    final minute = int.tryParse(parts[1]) ?? 0;
    return (
      hour.clamp(0, 23),
      minute.clamp(0, 59),
    );
  }

  int _previousWeekday(int weekday) {
    return weekday == DateTime.monday ? DateTime.sunday : (weekday - 1);
  }
}

class _ReminderTimeSlot {
  final int hour;
  final int minute;
  final bool shiftToPreviousDay;

  const _ReminderTimeSlot({
    required this.hour,
    required this.minute,
    required this.shiftToPreviousDay,
  });
}
