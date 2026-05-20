import 'package:flutter/material.dart';

/// Brand palette — mirrors the PDF reports (deep navy + warm gold) so the
/// app and the documents the client downloads share a visual language.
const Color _brandNavy   = Color(0xFF0E2A47);
const Color _brandGold   = Color(0xFFC9A246);
const Color _surfaceLight = Color(0xFFF7F8FB);
const Color _surfaceDark  = Color(0xFF0B1220);

ThemeData get shipflowLightTheme {
  final base = ThemeData.light(useMaterial3: true);
  return base.copyWith(
    colorScheme: const ColorScheme.light(
      primary: _brandNavy,
      secondary: _brandGold,
      surface: _surfaceLight,
    ),
    scaffoldBackgroundColor: _surfaceLight,
    appBarTheme: const AppBarTheme(
      backgroundColor: _brandNavy,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
    ),
    cardTheme: const CardThemeData(
      elevation: 1,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(12)),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: _brandNavy,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide.none,
      ),
    ),
  );
}

ThemeData get shipflowDarkTheme {
  final base = ThemeData.dark(useMaterial3: true);
  return base.copyWith(
    colorScheme: const ColorScheme.dark(
      primary: _brandGold,
      secondary: _brandGold,
      surface: _surfaceDark,
    ),
    scaffoldBackgroundColor: _surfaceDark,
    appBarTheme: const AppBarTheme(
      backgroundColor: _surfaceDark,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
    ),
  );
}
