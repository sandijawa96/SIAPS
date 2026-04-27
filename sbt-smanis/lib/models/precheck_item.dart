enum PrecheckStatus { checking, passed, warning, failed }

class PrecheckItem {
  const PrecheckItem({
    required this.id,
    required this.title,
    required this.description,
    required this.status,
    this.detail,
    this.required = true,
    this.actionLabel,
  });

  final String id;
  final String title;
  final String description;
  final PrecheckStatus status;
  final String? detail;
  final bool required;
  final String? actionLabel;

  bool get blocksExam => required && status == PrecheckStatus.failed;
}
