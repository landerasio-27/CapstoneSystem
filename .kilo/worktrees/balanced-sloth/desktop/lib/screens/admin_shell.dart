import 'package:flutter/material.dart';
import '../resources/resource_config.dart';
import '../resources/resource_registry.dart';
import '../services/auth_service.dart';
import '../theme/app_theme.dart';
import 'account_settings_screen.dart';
import 'dashboard_screen.dart';
import 'documents_screen.dart';
import 'inspection_records_screen.dart';
import 'inspections_screen.dart';
import 'login_screen.dart';
import 'qrcodes_screen.dart';
import 'reports_screen.dart';
import 'resource_list_screen.dart';

class _NavItem {
  final String label;
  final IconData icon;
  final WidgetBuilder builder;
  const _NavItem(this.label, this.icon, this.builder);
}

class _NavGroup {
  final String title;
  final List<_NavItem> items;
  const _NavGroup(this.title, this.items);
}

/// The main window: a fixed sidebar (mirrors the web portal's menu) plus the
/// selected section's content. Only Dashboard is implemented so far; every
/// other section is a labelled placeholder that will be filled in per resource.
class AdminShell extends StatefulWidget {
  const AdminShell({super.key});

  @override
  State<AdminShell> createState() => _AdminShellState();
}

class _AdminShellState extends State<AdminShell> {
  int _selected = 0;

  late final List<_NavGroup> _groups = [
    const _NavGroup('Overview', [
      _NavItem('Dashboard', Icons.grid_view_rounded, _dashboard),
    ]),
    _NavGroup('Inventory', [
      _navResource('Rooms', Resources.rooms),
      _navResource('Teachers', Resources.teachers),
      _navResource('Equipment', Resources.equipment),
      _navResource('Supplies', Resources.supplies),
      const _NavItem('QR Codes', Icons.qr_code_2, _qrcodes),
    ]),
    const _NavGroup('Operations', [
      _NavItem('Documents', Icons.description_outlined, _documents),
      _NavItem('Inspections', Icons.fact_check_outlined, _inspections),
      _NavItem('Inspection Records', Icons.assignment_outlined, _records),
    ]),
    _NavGroup('Insights', [
      const _NavItem('Reports', Icons.bar_chart_rounded, _reports),
      _navResource('Announcements', Resources.announcements),
      _navResource('Activity Logs', Resources.activityLogs),
    ]),
    _NavGroup('System', [
      _navResource('Users', Resources.users),
      const _NavItem('Account Settings', Icons.settings_outlined, _account),
    ]),
  ];

  static Widget _documents(BuildContext _) => const DocumentsScreen();
  static Widget _inspections(BuildContext _) => const InspectionsScreen();
  static Widget _records(BuildContext _) => const InspectionRecordsScreen();
  static Widget _reports(BuildContext _) => const ReportsScreen();
  static Widget _account(BuildContext _) => const AccountSettingsScreen();

  static Widget _dashboard(BuildContext _) => const DashboardScreen();
  static Widget _qrcodes(BuildContext _) => const QrCodesScreen();

  static _NavItem _navResource(String label, ResourceConfig config) => _NavItem(
        label,
        config.icon,
        (_) => ResourceListScreen(key: ValueKey(config.path), config: config),
      );

  List<_NavItem> get _flat => _groups.expand((g) => g.items).toList();

  Future<void> _logout() async {
    await AuthService.instance.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final current = _flat[_selected];
    return Scaffold(
      body: Row(
        children: [
          _Sidebar(
            groups: _groups,
            selectedLabel: current.label,
            onSelect: (label) {
              final idx = _flat.indexWhere((i) => i.label == label);
              if (idx >= 0) setState(() => _selected = idx);
            },
            onLogout: _logout,
          ),
          Expanded(
            child: Column(
              children: [
                _TopBar(title: current.label),
                Expanded(child: current.builder(context)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Sidebar extends StatelessWidget {
  final List<_NavGroup> groups;
  final String selectedLabel;
  final ValueChanged<String> onSelect;
  final VoidCallback onLogout;

  const _Sidebar({
    required this.groups,
    required this.selectedLabel,
    required this.onSelect,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    final user = AuthService.instance.user;
    return Container(
      width: 260,
      color: Colors.white,
      child: Column(
        children: [
          Container(
            height: 64,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            alignment: Alignment.centerLeft,
            decoration: BoxDecoration(
              border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
            ),
            child: Row(
              children: [
                const CircleAvatar(
                  radius: 18,
                  backgroundColor: AppTheme.surfaceTint,
                  child: Icon(Icons.inventory_2, size: 20, color: AppTheme.brand),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Supply Management',
                          style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                      Text('Cauayan Campus',
                          style: TextStyle(color: Colors.grey, fontSize: 11)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
              children: [
                for (final group in groups) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 6),
                    child: Text(
                      group.title.toUpperCase(),
                      style: TextStyle(
                        fontSize: 11,
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w700,
                        color: Colors.grey.shade500,
                      ),
                    ),
                  ),
                  for (final item in group.items)
                    _NavTile(
                      item: item,
                      selected: item.label == selectedLabel,
                      onTap: () => onSelect(item.label),
                    ),
                ],
              ],
            ),
          ),
          Divider(height: 1, color: Colors.grey.shade200),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 18,
                  backgroundColor: AppTheme.brand.withValues(alpha: 0.12),
                  child: Text(
                    _initials(user?.name ?? '?'),
                    style: const TextStyle(
                        color: AppTheme.brandDark, fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(user?.name ?? '',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                      Text(user?.role ?? '',
                          style: TextStyle(color: Colors.grey.shade500, fontSize: 11)),
                    ],
                  ),
                ),
                IconButton(
                  tooltip: 'Log out',
                  icon: const Icon(Icons.logout, size: 20),
                  onPressed: onLogout,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return parts.take(2).map((p) => p[0].toUpperCase()).join();
  }
}

class _NavTile extends StatelessWidget {
  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  const _NavTile({required this.item, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: Material(
        color: selected ? AppTheme.brand.withValues(alpha: 0.10) : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Row(
              children: [
                Icon(item.icon,
                    size: 20, color: selected ? AppTheme.brandDark : Colors.grey.shade600),
                const SizedBox(width: 12),
                Text(
                  item.label,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
                    color: selected ? AppTheme.brandDark : Colors.grey.shade800,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _TopBar extends StatelessWidget {
  final String title;
  const _TopBar({required this.title});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 64,
      padding: const EdgeInsets.symmetric(horizontal: 24),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
      ),
      alignment: Alignment.centerLeft,
      child: Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
    );
  }
}
