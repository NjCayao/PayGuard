import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radius.dart';
import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';

enum NoticeKind { ok, warning, error }

class NoticeBanner extends StatelessWidget {
  const NoticeBanner.ok(this.message, {super.key}) : kind = NoticeKind.ok;

  const NoticeBanner.warning(this.message, {super.key})
      : kind = NoticeKind.warning;

  const NoticeBanner.error(this.message, {super.key}) : kind = NoticeKind.error;

  final String message;
  final NoticeKind kind;

  @override
  Widget build(BuildContext context) {
    final Color background;
    final Color foreground;
    final IconData icon;

    switch (kind) {
      case NoticeKind.ok:
        background = AppColors.primarySoft;
        foreground = AppColors.primaryText;
        icon = Icons.check_circle_outline;
        break;
      case NoticeKind.warning:
        background = AppColors.warningSoft;
        foreground = AppColors.warningText;
        icon = Icons.warning_amber_outlined;
        break;
      case NoticeKind.error:
        background = AppColors.dangerSoft;
        foreground = AppColors.danger;
        icon = Icons.error_outline;
        break;
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.sm + 4),
      decoration: BoxDecoration(
        color: background,
        borderRadius: const BorderRadius.all(Radius.circular(AppRadius.md)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: foreground, size: 20),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              message,
              style: AppTextStyles.body.copyWith(color: foreground),
            ),
          ),
        ],
      ),
    );
  }
}
