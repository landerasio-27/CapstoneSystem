import 'package:flutter/material.dart';
import 'services/auth_service.dart';
import 'screens/admin_shell.dart';
import 'screens/login_screen.dart';
import 'theme/app_theme.dart';

void main() {
  runApp(const IsuSupplyDesktopApp());
}

class IsuSupplyDesktopApp extends StatelessWidget {
  const IsuSupplyDesktopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ISU Cauayan Supply Management',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      home: const _Gate(),
    );
  }
}

/// Decides where to start: an existing admin session goes straight to the
/// shell, otherwise the login screen.
class _Gate extends StatefulWidget {
  const _Gate();

  @override
  State<_Gate> createState() => _GateState();
}

class _GateState extends State<_Gate> {
  late final Future<bool> _restore = AuthService.instance.restore();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: _restore,
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        return (snap.data ?? false) ? const AdminShell() : const LoginScreen();
      },
    );
  }
}
