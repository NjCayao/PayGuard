import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_fonts.dart';
import 'app_radius.dart';
import 'app_text_styles.dart';

ThemeData buildAppTheme() {
  final colorScheme = ColorScheme.fromSeed(
    seedColor: AppColors.primary,
    brightness: Brightness.light,
  );

  const OutlineInputBorder fieldBorder = OutlineInputBorder(
    borderRadius: BorderRadius.all(Radius.circular(AppRadius.md)),
    borderSide: BorderSide(color: AppColors.fieldBorder),
  );

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    fontFamily: AppFonts.family,
    colorScheme: colorScheme.copyWith(
      primary: AppColors.primary,
      onPrimary: Colors.white,
      surface: AppColors.background,
    ),
    scaffoldBackgroundColor: AppColors.background,
    cardTheme: const CardThemeData(
      color: AppColors.background,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.md)),
        side: BorderSide(color: AppColors.border),
      ),
    ),
    inputDecorationTheme: const InputDecorationTheme(
      filled: false,
      border: fieldBorder,
      enabledBorder: fieldBorder,
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.md)),
        borderSide: BorderSide(color: AppColors.primary, width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.md)),
        borderSide: BorderSide(color: AppColors.warningText),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.md)),
        borderSide: BorderSide(color: AppColors.warningText, width: 1.5),
      ),
      hintStyle: AppTextStyles.bodyMuted,
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    dividerTheme: const DividerThemeData(
      color: AppColors.border,
      thickness: 1,
      space: 1,
    ),
  );
}
