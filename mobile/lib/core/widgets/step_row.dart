import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';

class StepRow extends StatelessWidget {
  const StepRow({
    super.key,
    required this.icon,
    required this.title,
    required this.detail,
    required this.done,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String detail;
  final bool done;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: done ? AppColors.successBackground : null,
              border: done
                  ? null
                  : Border.all(color: AppColors.warningDot, width: 1.5),
            ),
            child: Icon(
              done ? Icons.check : icon,
              color:
                  done ? AppColors.successDot : AppColors.warningDot,
              size: 20,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: AppTextStyles.body),
                Text(detail, style: AppTextStyles.detail),
              ],
            ),
          ),
          if (actionLabel != null && onAction != null)
            TextButton(
              onPressed: onAction,
              child: Text(actionLabel!, style: AppTextStyles.action),
            ),
        ],
      ),
    );
  }
}
