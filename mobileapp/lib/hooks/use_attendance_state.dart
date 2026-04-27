import 'package:flutter/material.dart';

class AttendanceState {
  static const Object _noChange = Object();

  final String? checkinTime;
  final String? checkoutTime;
  final bool isCheckedIn;

  const AttendanceState({
    this.checkinTime,
    this.checkoutTime,
    this.isCheckedIn = false,
  });

  AttendanceState copyWith({
    Object? checkinTime = _noChange,
    Object? checkoutTime = _noChange,
    bool? isCheckedIn,
  }) {
    return AttendanceState(
      checkinTime: identical(checkinTime, _noChange)
          ? this.checkinTime
          : checkinTime as String?,
      checkoutTime: identical(checkoutTime, _noChange)
          ? this.checkoutTime
          : checkoutTime as String?,
      isCheckedIn: isCheckedIn ?? this.isCheckedIn,
    );
  }
}

class UseAttendanceState extends ChangeNotifier {
  AttendanceState _state = const AttendanceState();

  AttendanceState get state => _state;

  void doCheckin([String? customTime]) {
    final now = DateTime.now();
    final timeString = customTime ??
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    _state = _state.copyWith(
      checkinTime: timeString,
      isCheckedIn: true,
    );
    notifyListeners();

    // Debug print
    debugPrint(
        '✅ Checkin state updated: ${_state.checkinTime}, isCheckedIn: ${_state.isCheckedIn}');
  }

  void doCheckout([String? customTime]) {
    final now = DateTime.now();
    final timeString = customTime ??
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

    _state = _state.copyWith(
      checkoutTime: timeString,
      isCheckedIn: false,
    );
    notifyListeners();

    // Debug print
    debugPrint(
        '✅ Checkout state updated: ${_state.checkoutTime}, isCheckedIn: ${_state.isCheckedIn}');
  }

  void updateFromBackendData({
    String? checkinTime,
    String? checkoutTime,
    bool? isCheckedIn,
  }) {
    _state = _state.copyWith(
      checkinTime: checkinTime,
      checkoutTime: checkoutTime,
      isCheckedIn: isCheckedIn,
    );
    notifyListeners();

    // Debug print
    debugPrint(
        '✅ Backend data updated: checkin=${_state.checkinTime}, checkout=${_state.checkoutTime}, isCheckedIn=${_state.isCheckedIn}');
  }

  void reset() {
    _state = const AttendanceState();
    notifyListeners();

    // Debug print
    debugPrint('✅ Attendance state reset');
  }
}
