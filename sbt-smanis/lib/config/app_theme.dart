import 'package:flutter/material.dart';

class AppColors {
  static const ink = Color(0xFF0F2A43);
  static const muted = Color(0xFF5D7083);
  static const line = Color(0xFFD8EAFB);
  static const surface = Color(0xFFFFFFFF);
  static const background = Color(0xFFF7FBFF);
  static const paper = Color(0xFFF7FBFF);
  static const primary = Color(0xFF1E88E5);
  static const primaryDark = Color(0xFF0F5CA7);
  static const accent = Color(0xFF21C7A8);
  static const softBlue = Color(0xFFE5F6FF);
  static const softMint = Color(0xFFEAF8F4);
  static const stamp = Color(0xFFB43757);
  static const warning = Color(0xFFD99A00);
  static const danger = Color(0xFFC83737);
}

ThemeData buildAppTheme() {
  return ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      secondary: AppColors.accent,
      error: AppColors.danger,
      surface: AppColors.surface,
    ),
    scaffoldBackgroundColor: AppColors.background,
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.transparent,
      foregroundColor: AppColors.ink,
      centerTitle: false,
      elevation: 0,
      scrolledUnderElevation: 0,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        minimumSize: const Size(64, 52),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.ink,
        side: const BorderSide(color: AppColors.line),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        minimumSize: const Size(64, 52),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
      ),
    ),
    textTheme: const TextTheme(
      headlineLarge: TextStyle(
        color: AppColors.ink,
        fontSize: 38,
        fontWeight: FontWeight.w900,
        height: 1.06,
        letterSpacing: 0,
      ),
      headlineMedium: TextStyle(
        color: AppColors.ink,
        fontSize: 30,
        fontWeight: FontWeight.w900,
        height: 1.08,
        letterSpacing: 0,
      ),
      titleLarge: TextStyle(
        color: AppColors.ink,
        fontSize: 20,
        fontWeight: FontWeight.w800,
        letterSpacing: 0,
      ),
      titleMedium: TextStyle(
        color: AppColors.ink,
        fontSize: 17,
        fontWeight: FontWeight.w700,
        letterSpacing: 0,
      ),
      bodyLarge: TextStyle(
        color: AppColors.ink,
        fontSize: 16,
        fontWeight: FontWeight.w500,
        height: 1.45,
        letterSpacing: 0,
      ),
      bodyMedium: TextStyle(
        color: AppColors.muted,
        fontSize: 14,
        fontWeight: FontWeight.w500,
        height: 1.45,
        letterSpacing: 0,
      ),
      labelLarge: TextStyle(
        color: AppColors.ink,
        fontSize: 15,
        fontWeight: FontWeight.w700,
        letterSpacing: 0,
      ),
    ),
  );
}
