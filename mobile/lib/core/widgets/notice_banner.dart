import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_radius.dart';
import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';

class NoticeBanner extends StatelessWidget {
  const NoticeBanner.ok(this.message, {super.key}) : ok = true;

  const NoticeBanner.error(this.message, {super.key}) : ok = false;

  final String message;
  final bool ok;

  @override
  Widget build(BuildContext context) {
    final Color background =
        ok ? AppColors.successBackground : AppColors.softBackground;
    final Color foreground =
        ok ? AppColors.primaryDark : AppColors.warningText;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.sm),
      decoration: BoxDecoration(
        color: background,
        borderRadius: const BorderRadius.all(Radius.circular(AppRadius.md)),
      ),
      child: Text(
        message,
        style: AppTextStyles.body.copyWith(color: foreground),
      ),
    );
  }
}
