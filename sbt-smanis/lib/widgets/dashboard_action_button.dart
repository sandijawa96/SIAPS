import 'package:flutter/material.dart';

import '../config/app_theme.dart';

class DashboardActionButton extends StatelessWidget {
  const DashboardActionButton({
    required this.icon,
    required this.title,
    required this.description,
    required this.onPressed,
    this.isPrimary = false,
    this.height = 86,
    this.showDescription = true,
    super.key,
  });

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onPressed;
  final bool isPrimary;
  final double height;
  final bool showDescription;

  @override
  Widget build(BuildContext context) {
    final borderColor = isPrimary ? AppColors.primary : Colors.white;
    final iconColor = isPrimary ? AppColors.primary : AppColors.accent;
    final compact = height < 70;
    final iconSize = compact ? 38.0 : 50.0;
    final showBody = showDescription && !compact;

    return SizedBox(
      height: height,
      child: Material(
        color: Colors.white.withValues(alpha: isPrimary ? 0.96 : 0.86),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
          side: BorderSide(color: borderColor, width: isPrimary ? 1.5 : 1),
        ),
        elevation: 0,
        child: InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: onPressed,
          child: Padding(
            padding: EdgeInsets.symmetric(
              horizontal: compact ? 10 : 16,
              vertical: compact ? 8 : 10,
            ),
            child: Row(
              children: [
                Container(
                  width: iconSize,
                  height: iconSize,
                  decoration: BoxDecoration(
                    color: isPrimary
                        ? AppColors.softBlue
                        : iconColor.withValues(alpha: 0.11),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isPrimary
                          ? const Color(0xFFC8EBFF)
                          : iconColor.withValues(alpha: 0.18),
                    ),
                  ),
                  child: Icon(icon, color: iconColor, size: compact ? 21 : 26),
                ),
                SizedBox(width: compact ? 10 : 14),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              fontWeight: FontWeight.w900,
                              fontSize: compact ? 15 : null,
                            ),
                      ),
                      if (showBody) ...[
                        const SizedBox(height: 4),
                        Text(
                          description,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ],
                  ),
                ),
                SizedBox(width: compact ? 6 : 8),
                Icon(
                  Icons.arrow_forward_rounded,
                  size: compact ? 20 : 24,
                  color: isPrimary ? AppColors.primary : AppColors.muted,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
