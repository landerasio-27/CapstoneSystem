import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Brand color tokens — light theme only. The app is intentionally light-mode
/// only; there is no dark palette. Every `AppColors.x` reference across the app
/// resolves to these fixed values.
class AppColors {
  AppColors._();

  static const Color primary = Color(0xFF2E7D32);
  static const Color primaryDark = Color(0xFF1B5E20);
  static const Color secondary = Color(0xFF66BB6A);
  static const Color background = Color(0xFFFFFFFF);
  static const Color surfaceTint = Color(0xFFF1F8F2);
  static const Color card = Color(0xFFFFFFFF);
  static const Color fieldFill = Color(0xFFF1F8F2);
  static const Color textPrimary = Color(0xFF1F2937);
  static const Color textSecondary = Color(0xFF6B7280);
  static const Color onPrimary = Color(0xFFFFFFFF);
  static const Color verified = Color(0xFF2E7D32);
  static const Color misplaced = Color(0xFFF59E0B);
  static const Color missing = Color(0xFFDC2626);
  static const Color border = Color(0xFFE5E7EB);
  static const Color divider = Color(0xFFF1F1F1);
}

/// System status/navigation bar styling for light backgrounds (dark icons).
/// Applied globally in main() and via the AppBar theme so every screen matches.
const SystemUiOverlayStyle appSystemOverlay = SystemUiOverlayStyle(
  statusBarColor: Colors.transparent,
  statusBarIconBrightness: Brightness.dark, // Android: dark icons on light bar
  statusBarBrightness: Brightness.light, // iOS
  systemNavigationBarColor: Color(0xFFFFFFFF),
  systemNavigationBarIconBrightness: Brightness.dark,
);

class AppTheme {
  AppTheme._();

  static const double pagePadding = 16;
  static const double cardRadius = 16;

  /// The single, app-wide light theme.
  static ThemeData get light {
    final base = ThemeData.light(useMaterial3: true);

    final colorScheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      onPrimary: AppColors.onPrimary,
      secondary: AppColors.secondary,
      surface: AppColors.card,
      onSurface: AppColors.textPrimary,
      error: AppColors.missing,
      brightness: Brightness.light,
    );

    return base.copyWith(
      colorScheme: colorScheme,
      scaffoldBackgroundColor: AppColors.surfaceTint,
      primaryColor: AppColors.primary,
      dividerColor: AppColors.divider,
      canvasColor: AppColors.card,
      dialogTheme: const DialogThemeData(backgroundColor: AppColors.card),

      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.surfaceTint,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        systemOverlayStyle: appSystemOverlay,
        titleTextStyle: TextStyle(color: AppColors.textPrimary, fontSize: 18, fontWeight: FontWeight.w600),
        iconTheme: IconThemeData(color: AppColors.textPrimary),
      ),

      textTheme: base.textTheme
          .apply(bodyColor: AppColors.textPrimary, displayColor: AppColors.textPrimary)
          .copyWith(
            titleLarge: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.textPrimary),
            titleMedium: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary),
            bodyMedium: const TextStyle(color: AppColors.textPrimary),
            bodySmall: const TextStyle(color: AppColors.textSecondary),
          ),

      cardTheme: CardThemeData(
        color: AppColors.card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(cardRadius)),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: AppColors.onPrimary,
          minimumSize: const Size.fromHeight(52),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primary,
          side: const BorderSide(color: AppColors.primary, width: 1.4),
          minimumSize: const Size.fromHeight(52),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: AppColors.primary),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.fieldFill,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: const TextStyle(color: AppColors.textSecondary),
        labelStyle: const TextStyle(color: AppColors.textSecondary),
        prefixIconColor: AppColors.textSecondary,
        suffixIconColor: AppColors.textSecondary,
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.6),
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
      ),

      chipTheme: base.chipTheme.copyWith(
        backgroundColor: AppColors.fieldFill,
        labelStyle: const TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w500),
        side: BorderSide.none,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(color: AppColors.primary),

      bottomSheetTheme: const BottomSheetThemeData(backgroundColor: AppColors.card),

      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),

      listTileTheme: const ListTileThemeData(
        iconColor: AppColors.textSecondary,
        textColor: AppColors.textPrimary,
      ),
    );
  }

  /// Soft background + text color pair for a given inspection-item status.
  static (Color, Color) statusColors(String status) {
    switch (status) {
      case 'verified':
        return (AppColors.verified.withValues(alpha: 0.12), AppColors.verified);
      case 'misplaced':
        return (AppColors.misplaced.withValues(alpha: 0.14), AppColors.misplaced);
      case 'missing':
        return (AppColors.missing.withValues(alpha: 0.12), AppColors.missing);
      default:
        return (AppColors.border.withValues(alpha: 0.6), AppColors.textSecondary);
    }
  }
}

/// Reusable card: white surface with a soft shadow.
class AppCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.card,
      borderRadius: BorderRadius.circular(AppTheme.cardRadius),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppTheme.cardRadius),
        onTap: onTap,
        child: Ink(
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(AppTheme.cardRadius),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          padding: padding,
          child: child,
        ),
      ),
    );
  }
}

/// Small colored status pill used in equipment / inspection lists.
class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = AppTheme.statusColors(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Text(
        status.isEmpty ? '—' : status[0].toUpperCase() + status.substring(1),
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}
