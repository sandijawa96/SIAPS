class GuardEvent {
  const GuardEvent({
    required this.type,
    required this.message,
    required this.occurredAt,
  });

  factory GuardEvent.fromMap(Map<dynamic, dynamic> value) {
    return GuardEvent(
      type: value['type']?.toString() ?? 'UNKNOWN',
      message: value['message']?.toString() ?? 'Aktivitas perangkat berubah.',
      occurredAt: DateTime.now(),
    );
  }

  final String type;
  final String message;
  final DateTime occurredAt;
}
