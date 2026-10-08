import 'package:shared_preferences/shared_preferences.dart';

/// Remembers whether the first-launch onboarding slideshow has been completed.
class OnboardingPrefs {
  OnboardingPrefs._();

  static const String _key = 'onboarding_completed';

  /// Set to `true` to force the slideshow to appear on EVERY launch
  /// (handy for a capstone demo). Leave `false` for normal first-launch-only.
  static const bool showOnboardingEveryLaunch = false;

  /// Whether the onboarding slideshow should be shown now.
  static Future<bool> shouldShow() async {
    if (showOnboardingEveryLaunch) return true;
    final prefs = await SharedPreferences.getInstance();
    return !(prefs.getBool(_key) ?? false);
  }

  /// Mark onboarding as done so it won't show again on later launches.
  static Future<void> markComplete() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_key, true);
  }
}
