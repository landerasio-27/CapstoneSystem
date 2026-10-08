import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../services/push_service.dart';
import '../state/inbox_controller.dart';
import '../theme/app_theme.dart';
import '../widgets/loading_overlay.dart';
import 'login_gate.dart';
import 'server_settings_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final user = auth.user;

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: Text('Profile'), backgroundColor: AppColors.surfaceTint),
      body: SafeArea(
        top: false,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
          children: [
            // Avatar + identity
            AppCard(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 40,
                    backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                    child: Text(
                      _initials(user?.name ?? ''),
                      style: TextStyle(color: AppColors.primary, fontSize: 26, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(user?.name ?? '',
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                  const SizedBox(height: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      'Inspection Staff',
                      style: TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Account details
            AppCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  _tile(Icons.email_outlined, 'Email', user?.email ?? '—'),
                  const Divider(height: 1, indent: 56),
                  _tile(Icons.badge_outlined, 'Role', 'Inspection Staff'),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Settings
            AppCard(
              padding: EdgeInsets.zero,
              child: Column(
                children: [
                  ListTile(
                    leading: Icon(Icons.dns_outlined, color: AppColors.textSecondary),
                    title: const Text('Server Settings'),
                    subtitle: const Text('Find the ISU server on this network'),
                    trailing: Icon(Icons.chevron_right, color: AppColors.textSecondary),
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const ServerSettingsScreen()),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            OutlinedButton.icon(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (context) => AlertDialog(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    title: const Text('Log out?'),
                    content: const Text('You will need to sign in again to run inspections.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
                      FilledButton(
                        style: FilledButton.styleFrom(backgroundColor: AppColors.missing, minimumSize: Size(88, 44)),
                        onPressed: () => Navigator.pop(context, true),
                        child: const Text('Log out'),
                      ),
                    ],
                  ),
                );
                if (confirm != true || !context.mounted) return;

                // Capture references before any await to avoid using context across async gaps.
                final auth = context.read<AuthService>();
                final inbox = context.read<InboxController>();
                final navigator = Navigator.of(context);

                // Full-screen "Logging out…" overlay blocks interaction while we
                // unregister the device, hit the logout API and clear the session.
                LoadingOverlay.show(context, message: 'Logging out…');
                try {
                  await PushService.instance.unregister();
                } catch (_) {
                  // Ignore push errors — still clear the session below.
                }
                inbox.reset();
                // auth.logout() already clears the local session even if the API
                // call fails, so the user is never trapped on a network error.
                await auth.logout();

                LoadingOverlay.hide();
                navigator.pushAndRemoveUntil(
                  MaterialPageRoute(builder: (_) => const LoginGate()),
                  (route) => false,
                );
              },
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.missing,
                side: BorderSide(color: AppColors.missing, width: 1.4),
              ),
              icon: const Icon(Icons.logout_rounded),
              label: const Text('Log out'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _tile(IconData icon, String label, String value) {
    return ListTile(
      leading: Icon(icon, color: AppColors.textSecondary),
      title: Text(label, style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
      subtitle: Text(value,
          style: TextStyle(fontSize: 15, color: AppColors.textPrimary, fontWeight: FontWeight.w500)),
    );
  }
}
