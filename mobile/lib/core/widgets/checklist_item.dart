import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';
import 'app_card.dart';

enum ChecklistStatus { ok, pending }

class ChecklistItem extends StatelessWidget {
  const ChecklistItem({
    super.key,
    required this.title,
    required this.description,
    required this.status,
    this.actionLabel,
    this.onAction,
    this.extra,
  });

  final String title;
  final String description;
  final ChecklistStatus status;
  final String? actionLabel;
  final VoidCallback? onAction;
  final Widget? extra;

  @override
  Widget build(BuildContext context) {
    final bool ok = status == ChecklistStatus.ok;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                ok ? Icons.check_circle : Icons.radio_button_unchecked,
                color: ok ? AppColors.ok : AppColors.idle,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: AppTextStyles.subtitle),
                    const SizedBox(width: AppSpacing.xs),
                    Text(description, style: AppTextStyles.muted),
                  ],
                ),
              ),
            ],
          ),
          if (extra != null) ...[
            const SizedBox(height: AppSpacing.sm),
            extra!,
          ],
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: AppSpacing.sm),
            Align(
              alignment: Alignment.centerRight,
              child: OutlinedButton(
                onPressed: onAction,
                child: Text(actionLabel!),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
