import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'services/auth_service.dart';
import 'services/push_service.dart';
import 'state/app_events.dart';
import 'state/inbox_controller.dart';
import 'theme/app_theme.dart';
import 'onboarding/onboarding_prefs.dart';
import 'onboarding/onboarding_screen.dart';
import 'screens/login_gate.dart';
import 'screens/main_scaffold.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Light theme only: force light system bars (dark icons on light bars).
  SystemChrome.setSystemUIOverlayStyle(appSystemOverlay);

  // Initialize Firebase. With a placeholder google-services.json this still
  // succeeds locally (it just configures options); real push delivery begins
  // once a real Firebase project's google-services.json is dropped in. Any
  // failure is swallowed so the app runs normally without FCM.
  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    firebaseReady = true;
  } catch (_) {
    firebaseReady = false;
  }

  // One-time cleanup: the app is light-only now, so drop any saved theme choice.
  try {
    await (await SharedPreferences.getInstance()).remove('theme_mode');
  } catch (_) {}

  // Decide up-front whether the first-launch onboarding slideshow is pending.
  final onboardingPending = await OnboardingPrefs.shouldShow();

  runApp(MyApp(onboardingPending: onboardingPending));
}

class MyApp extends StatelessWidget {
  final bool onboardingPending;

  const MyApp({super.key, required this.onboardingPending});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthService()..restoreSession()),
        ChangeNotifierProvider(create: (_) => InboxController()),
      ],
      child: MaterialApp(
        title: 'ISU Inspection',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        // Light theme only — never follow the system's dark setting.
        themeMode: ThemeMode.light,
        home: _RootScreen(onboardingPending: onboardingPending),
      ),
    );
  }
}

class _RootScreen extends StatelessWidget {
  final bool onboardingPending;

  const _RootScreen({required this.onboardingPending});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();

    // Splash is still finishing (restoring the saved session).
    if (auth.initializing) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    // Logged in → straight to Home (skip the slideshow).
    if (auth.isLoggedIn) return const MainScaffold();

    // First launch (not logged in) → show the onboarding slideshow, which then
    // navigates on to the login gate. Otherwise go through the login gate,
    // which verifies the saved server (or runs automatic discovery) before
    // showing the login form.
    if (onboardingPending) return const OnboardingScreen();

    return const LoginGate();
  }
}
