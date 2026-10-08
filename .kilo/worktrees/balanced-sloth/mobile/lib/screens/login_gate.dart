import 'package:flutter/material.dart';

import '../services/server_discovery.dart';
import '../theme/app_theme.dart';
import 'login_screen.dart';
import 'server_discovery_screen.dart';

/// Startup gate shown before the login screen.
///
/// 1. If a server was discovered before, quickly verify it is still reachable
///    and go straight to login.
/// 2. If not (first launch, or the server is gone / changed IP), run the
///    radar discovery screen — the user never has to type an address.
class LoginGate extends StatefulWidget {
  const LoginGate({super.key});

  @override
  State<LoginGate> createState() => _LoginGateState();
}

class _LoginGateState extends State<LoginGate> {
  bool _checking = true;
  bool _connected = false;

  @override
  void initState() {
    super.initState();
    _check();
  }

  Future<void> _check() async {
    final reachable = await ServerDiscovery.isSavedServerReachable();
    if (!mounted) return;
    setState(() {
      _checking = false;
      _connected = reachable;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) {
      return Scaffold(
        backgroundColor: Colors.white,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Image.asset('assets/images/logo_round.png', height: 84, width: 84),
              const SizedBox(height: 22),
              const SizedBox(
                width: 26,
                height: 26,
                child: CircularProgressIndicator(strokeWidth: 2.6, color: AppColors.primary),
              ),
              const SizedBox(height: 14),
              const Text('Connecting to server…',
                  style: TextStyle(fontSize: 13.5, color: AppColors.textSecondary)),
            ],
          ),
        ),
      );
    }

    if (!_connected) {
      return ServerDiscoveryScreen(onConnected: () => setState(() => _connected = true));
    }

    return const LoginScreen();
  }
}