import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_fonts.dart';

class AppTextStyles {
  static const TextStyle title = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 24,
    fontWeight: FontWeight.bold,
    color: AppColors.text,
  );

  static const TextStyle body = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 16,
    color: AppColors.text,
  );

  static const TextStyle bodyBold = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 16,
    fontWeight: FontWeight.bold,
    color: AppColors.text,
  );

  static const TextStyle bodyMuted = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 16,
    color: AppColors.muted,
  );

  static const TextStyle detail = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 13,
    color: AppColors.muted,
  );

  static const TextStyle label = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 13,
    fontWeight: FontWeight.w600,
    color: AppColors.label,
  );

  static const TextStyle button = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 16,
    fontWeight: FontWeight.w600,
    color: Colors.white,
  );

  static const TextStyle action = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 16,
    fontWeight: FontWeight.w600,
    color: AppColors.primary,
  );

  static const TextStyle brand = TextStyle(
    fontFamily: AppFonts.family,
    fontSize: 28,
    fontWeight: FontWeight.bold,
  );
}
