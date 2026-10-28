import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';

class StatusHeader extends StatelessWidget {
  const StatusHeader({
    super.key,
    required this.dotColor,
    required this.label,
    required this.labelColor,
    required this.title,
    required this.subtitle,
  });

  final Color dotColor;
  final String label;
  final Color labelColor;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: dotColor,
              ),
            ),
            const SizedBox(width: AppSpacing.xs),
            Text(
              label,
              style: AppTextStyles.detail.copyWith(color: labelColor),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(title, style: AppTextStyles.title),
        const SizedBox(height: AppSpacing.xs),
        Text(subtitle, style: AppTextStyles.bodyMuted),
      ],
    );
  }
}
