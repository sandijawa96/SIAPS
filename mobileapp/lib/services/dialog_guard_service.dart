class DialogGuardService {
  DialogGuardService._();

  static final DialogGuardService instance = DialogGuardService._();

  int _attendanceSubmissionLockCount = 0;

  bool get isAttendanceSubmissionInProgress =>
      _attendanceSubmissionLockCount > 0;

  void beginAttendanceSubmission() {
    _attendanceSubmissionLockCount += 1;
  }

  void endAttendanceSubmission() {
    if (_attendanceSubmissionLockCount <= 0) {
      _attendanceSubmissionLockCount = 0;
      return;
    }

    _attendanceSubmissionLockCount -= 1;
  }
}
