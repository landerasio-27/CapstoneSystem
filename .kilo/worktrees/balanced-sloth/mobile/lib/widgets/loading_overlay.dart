import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

/// A reusable full-screen blocking loader: a dim backdrop with a centered card
/// containing a green spinner and a message. It blocks all interaction and the
/// back button while visible.
///
/// Usage:
///   LoadingOverlay.show(context, message: 'Logging out…');
///   ... do async work ...
///   LoadingOverlay.hide(context);
class LoadingOverlay {
  LoadingOverlay._();

  static bool _visible = false;
  static NavigatorState? _navigator;

  static void show(BuildContext context, {String message = 'Please wait…'}) {
    if (_visible) return;
    _visible = true;
    // Capture the root navigator now so hide() needs no BuildContext later
    // (avoids using a context across async gaps).
    _navigator = Navigator.of(context, rootNavigator: true);

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      barrierColor: Colors.black.withValues(alpha: 0.5),
      useRootNavigator: true,
      builder: (_) => PopScope(canPop: false, child: _LoadingCard(message: message)),
    );
  }

  static void hide() {
    if (!_visible) return;
    _visible = false;
    _navigator?.pop();
    _navigator = null;
  }
}

class _LoadingCard extends StatelessWidget {
  final String message;

  const _LoadingCard({required this.message});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Material(
        color: Colors.transparent,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 26),
          decoration: BoxDecoration(
            color: AppColors.card,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 24, offset: const Offset(0, 8)),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 42,
                height: 42,
                child: CircularProgressIndicator(strokeWidth: 3.2, color: AppColors.primary),
              ),
              const SizedBox(height: 18),
              Text(
                message,
                style: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w600, fontSize: 14),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
