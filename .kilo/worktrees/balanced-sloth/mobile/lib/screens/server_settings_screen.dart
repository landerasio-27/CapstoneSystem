import 'package:flutter/material.dart';

import '../services/server_discovery.dart';
import '../theme/app_theme.dart';
import '../widgets/manual_server_dialog.dart';
import 'server_discovery_screen.dart';

/// Connection screen: shows the currently discovered ISU server, offers a
/// "Rediscover Server" radar scan (the primary path), and keeps manual entry
/// as a secondary fallback only.
class ServerSettingsScreen extends StatefulWidget {
  const ServerSettingsScreen({super.key});

  @override
  State<ServerSettingsScreen> createState() => _ServerSettingsScreenState();
}

class _ServerSettingsScreenState extends State<ServerSettingsScreen> {
  bool _loading = true;
  DiscoveredServer? _saved;
  DateTime? _savedAt;
  bool _reachable = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final info = await ServerDiscovery.savedInfo();
    final at = await ServerDiscovery.savedAt();
    final reachable = info != null && await ServerDiscovery.isSavedServerReachable();
    if (!mounted) return;
    setState(() {
      _loading = false;
      _saved = info;
      _savedAt = at;
      _reachable = reachable;
    });
  }

  Future<void> _rediscover() async {
    final ok = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const ServerDiscoveryScreen()),
    );
    if (ok == true) _load();
  }

  Future<void> _manualEntry() async {
    final ok = await showManualServerDialog(context);
    if (ok == true) _load();
  }

  String _fmt(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)}  ${two(d.hour)}:${two(d.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: const Text('Server Settings')),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : SafeArea(
              top: false,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  AppCard(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.dns_outlined, color: AppColors.primary),
                            const SizedBox(width: 10),
                            const Text('Connected Server',
                                style: TextStyle(
                                    fontWeight: FontWeight.w700, color: AppColors.textPrimary, fontSize: 16)),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (_saved == null)
                          const Text(
                            'No server configured yet. Tap "Rediscover Server" to '
                            'search your network automatically.',
                            style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
                          )
                        else ...[
                          Row(
                            children: [
                              Container(
                                width: 10,
                                height: 10,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: _reachable ? AppColors.verified : AppColors.misplaced,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                _reachable ? 'Connected' : 'Not reachable',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w700,
                                  color: _reachable ? AppColors.verified : AppColors.misplaced,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          _infoRow('Service', _saved!.service),
                          _infoRow('Server address', _saved!.host),
                          if (_savedAt != null) _infoRow('Saved', _fmt(_savedAt!)),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: _rediscover,
                    icon: const Icon(Icons.radar_rounded),
                    label: const Text('Rediscover Server'),
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: _manualEntry,
                    icon: const Icon(Icons.keyboard_rounded, size: 20),
                    label: const Text('Enter Server Manually'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.textSecondary,
                      side: const BorderSide(color: AppColors.border),
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'The app finds the ISU Cauayan server automatically. '
                    'Manual entry is only needed if automatic search fails.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(label, style: const TextStyle(fontSize: 13, color: AppColors.textSecondary)),
          ),
          Expanded(
            child: Text(value,
                style: const TextStyle(
                    fontSize: 13.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
          ),
        ],
      ),
    );
  }
}