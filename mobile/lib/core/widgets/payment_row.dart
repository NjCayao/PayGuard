import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';
import '../theme/app_text_styles.dart';

class PaymentRow extends StatelessWidget {
  const PaymentRow({
    super.key,
    required this.name,
    required this.detail,
    required this.amount,
  });

  final String name;
  final String detail;
  final String amount;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: AppTextStyles.bodyBold),
                Text(detail, style: AppTextStyles.detail),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Text(amount, style: AppTextStyles.bodyBold),
        ],
      ),
    );
  }
}
