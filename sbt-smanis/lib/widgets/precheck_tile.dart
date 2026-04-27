import 'package:flutter/material.dart';

import '../config/app_theme.dart';
import '../models/precheck_item.dart';

class PrecheckTile extends StatelessWidget {
  const PrecheckTile({required this.item, this.onAction, super.key});

  final PrecheckItem item;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final color = switch (item.status) {
      PrecheckStatus.checking => AppColors.accent,
      PrecheckStatus.passed => AppColors.primary,
      PrecheckStatus.warning => AppColors.warning,
      PrecheckStatus.failed => AppColors.danger,
    };

    final icon = switch (item.status) {
      PrecheckStatus.checking => Icons.sync,
      PrecheckStatus.passed => Icons.check_circle,
      PrecheckStatus.warning => Icons.warning_amber_rounded,
      PrecheckStatus.failed => Icons.cancel,
    };

    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        border: Border.all(color: Colors.white, width: 1.4),
        borderRadius: BorderRadius.circular(8),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F2A43),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 4),
                Text(
                  item.description,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                if (item.detail != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    item.detail!,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (item.actionLabel != null && onAction != null) ...[
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: onAction,
                    child: Text(item.actionLabel!),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
