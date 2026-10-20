import 'package:flutter/material.dart';

/// Colors of the A «Sokin akademik» UI kit, shared with the web admin and board.
@immutable
class AppColors extends ThemeExtension<AppColors> {
  const AppColors({
    required this.bg,
    required this.surface,
    required this.surface2,
    required this.ink,
    required this.ink2,
    required this.ink3,
    required this.line,
    required this.accent,
    required this.onAccent,
    required this.accentSoft,
    required this.pass,
    required this.passSoft,
    required this.warn,
    required this.warnSoft,
    required this.danger,
    required this.dangerSoft,
    required this.info,
    required this.infoSoft,
  });

  final Color bg;
  final Color surface;
  final Color surface2;
  final Color ink;
  final Color ink2;
  final Color ink3;
  final Color line;
  final Color accent;
  final Color onAccent;
  final Color accentSoft;
  final Color pass;
  final Color passSoft;
  final Color warn;
  final Color warnSoft;
  final Color danger;
  final Color dangerSoft;
  final Color info;
  final Color infoSoft;

  static const light = AppColors(
    bg: Color(0xFFF4F3EE),
    surface: Color(0xFFFFFFFF),
    surface2: Color(0xFFEFEDE6),
    ink: Color(0xFF1A1D24),
    ink2: Color(0xFF5B606B),
    ink3: Color(0xFF8A8F99),
    line: Color(0xFFDEDBD2),
    accent: Color(0xFF0E6B6B),
    onAccent: Color(0xFFFFFFFF),
    accentSoft: Color(0xFFDCEDEB),
    pass: Color(0xFF1F7A3D),
    passSoft: Color(0xFFE1F1E5),
    warn: Color(0xFFB4530A),
    warnSoft: Color(0xFFFBEBDD),
    danger: Color(0xFFB42318),
    dangerSoft: Color(0xFFFDE8E6),
    info: Color(0xFF2B5FB4),
    infoSoft: Color(0xFFE3ECFA),
  );

  static const dark = AppColors(
    bg: Color(0xFF101317),
    surface: Color(0xFF181C22),
    surface2: Color(0xFF222730),
    ink: Color(0xFFECEDEF),
    ink2: Color(0xFFA1A7B2),
    ink3: Color(0xFF6B717C),
    line: Color(0xFF2C323C),
    accent: Color(0xFF4FC2BC),
    onAccent: Color(0xFF062322),
    accentSoft: Color(0xFF143534),
    pass: Color(0xFF5BCB82),
    passSoft: Color(0xFF16301F),
    warn: Color(0xFFF0A260),
    warnSoft: Color(0xFF3A2614),
    danger: Color(0xFFF97066),
    dangerSoft: Color(0xFF3B1715),
    info: Color(0xFF7FA8F0),
    infoSoft: Color(0xFF172540),
  );

  /// Score color: green from the pass score up, orange below it.
  Color score(int? score, {int passScore = 60}) => score == null ? ink3 : (score >= passScore ? pass : warn);

  @override
  AppColors copyWith() => this;

  @override
  AppColors lerp(AppColors? other, double t) => t < 0.5 || other == null ? this : other;
}

extension AppColorsContext on BuildContext {
  AppColors get colors => Theme.of(this).extension<AppColors>()!;
}

ThemeData buildTheme(Brightness brightness) {
  final c = brightness == Brightness.dark ? AppColors.dark : AppColors.light;
  final base = ThemeData(brightness: brightness, useMaterial3: true);
  final radius = BorderRadius.circular(12);

  return base.copyWith(
    scaffoldBackgroundColor: c.bg,
    colorScheme: ColorScheme.fromSeed(
      seedColor: c.accent,
      brightness: brightness,
    ).copyWith(primary: c.accent, onPrimary: c.onAccent, surface: c.surface, onSurface: c.ink, error: c.danger, outline: c.line),
    extensions: [c],
    textTheme: base.textTheme
        .apply(bodyColor: c.ink, displayColor: c.ink)
        .copyWith(
          headlineSmall: base.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: c.ink, letterSpacing: -0.4),
          titleLarge: base.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: c.ink),
          titleMedium: base.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700, color: c.ink),
          bodySmall: base.textTheme.bodySmall?.copyWith(color: c.ink2),
        ),
    appBarTheme: AppBarTheme(
      backgroundColor: c.bg,
      foregroundColor: c.ink,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: c.ink),
    ),
    cardTheme: CardThemeData(
      color: c.surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: c.line),
      ),
    ),
    dividerTheme: DividerThemeData(color: c.line, space: 1, thickness: 1),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: c.accent,
        foregroundColor: c.onAccent,
        disabledBackgroundColor: c.accent.withValues(alpha: 0.4),
        disabledForegroundColor: c.onAccent,
        minimumSize: const Size(48, 52),
        textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: radius),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: c.ink,
        side: BorderSide(color: c.line),
        minimumSize: const Size(48, 48),
        textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: radius),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: c.accent,
        textStyle: const TextStyle(fontWeight: FontWeight.w700),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: c.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: c.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: c.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: c.accent, width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: c.danger),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: c.danger, width: 2),
      ),
      labelStyle: TextStyle(color: c.ink2),
      hintStyle: TextStyle(color: c.ink3),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.surface,
      indicatorColor: c.accentSoft,
      surfaceTintColor: Colors.transparent,
      iconTheme: WidgetStateProperty.resolveWith((states) => IconThemeData(color: states.contains(WidgetState.selected) ? c.accent : c.ink2)),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: states.contains(WidgetState.selected) ? c.accent : c.ink2),
      ),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: c.ink,
      contentTextStyle: TextStyle(color: c.bg, fontWeight: FontWeight.w600),
      shape: RoundedRectangleBorder(borderRadius: radius),
    ),
    bottomSheetTheme: BottomSheetThemeData(backgroundColor: c.surface, showDragHandle: true),
    dialogTheme: DialogThemeData(backgroundColor: c.surface),
    sliderTheme: SliderThemeData(activeTrackColor: c.accent, thumbColor: c.accent, inactiveTrackColor: c.line, trackHeight: 6),
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith((states) => states.contains(WidgetState.selected) ? c.onAccent : c.ink3),
      trackColor: WidgetStateProperty.resolveWith((states) => states.contains(WidgetState.selected) ? c.accent : c.surface2),
    ),
    checkboxTheme: CheckboxThemeData(
      fillColor: WidgetStateProperty.resolveWith((states) => states.contains(WidgetState.selected) ? c.accent : Colors.transparent),
      side: BorderSide(color: c.ink3, width: 1.6),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(5)),
    ),
  );
}
