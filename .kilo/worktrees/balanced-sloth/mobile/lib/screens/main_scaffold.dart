import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../theme/app_theme.dart';
import '../services/inspection_service.dart';
import '../services/push_service.dart';
import '../state/app_events.dart';
import '../state/inbox_controller.dart';
import 'home_screen.dart';
import 'notifications_screen.dart';
import 'history_screen.dart';
import 'profile_screen.dart';
import 'scan_screen.dart';
import 'teacher_preview_screen.dart';
import 'inspection_detail_screen.dart';

/// App shell: a fixed bottom navigation bar with a raised, docked circular
/// QR-scan button in the middle (e-wallet style). The center button starts
/// the inspection flow from anywhere in the app.
class MainScaffold extends StatefulWidget {
  const MainScaffold({super.key});

  @override
  State<MainScaffold> createState() => _MainScaffoldState();
}

class _MainScaffoldState extends State<MainScaffold> with WidgetsBindingObserver {
  final _inspectionService = InspectionService();
  int _index = 0;
  bool _scanning = false;

  late final List<Widget> _pages = [
    HomeScreen(onStartInspection: _startInspectionFlow),
    const NotificationsScreen(),
    const HistoryScreen(),
    const ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    notificationsChanged.addListener(_refreshBadge);
    pendingAnnouncementId.addListener(_onPendingAnnouncement);
    pendingInspectionId.addListener(_onPendingInspection);

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      // Push setup runs post-login (this shell only mounts when authenticated).
      await PushService.instance.initialize();
      await PushService.instance.requestPermission();
      await PushService.instance.registerToken();
      _refreshBadge();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    notificationsChanged.removeListener(_refreshBadge);
    pendingAnnouncementId.removeListener(_onPendingAnnouncement);
    pendingInspectionId.removeListener(_onPendingInspection);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshBadge();
    }
  }

  void _refreshBadge() {
    if (mounted) context.read<InboxController>().refresh();
  }

  void _onPendingAnnouncement() {
    // A push was tapped -> jump to the Inbox tab (the Inbox opens the detail).
    if (pendingAnnouncementId.value != null && mounted) {
      setState(() => _index = 1);
      context.read<InboxController>().markAllRead();
    }
  }

  void _onPendingInspection() {
    // An inspection push was tapped -> open that inspection's detail screen.
    final id = pendingInspectionId.value;
    if (id != null && mounted) {
      pendingInspectionId.value = null;
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => InspectionDetailScreen(inspectionId: id)),
      );
    }
  }

  void _onSelectTab(int i) {
    setState(() => _index = i);
    if (i == 1) {
      // Opening the Inbox clears the unread badge.
      context.read<InboxController>().markAllRead();
    }
  }

  Future<void> _startInspectionFlow() async {
    if (_scanning) return;

    final code = await Navigator.of(context).push<String>(
      MaterialPageRoute(
        builder: (_) => const ScanScreen(
          title: 'Scan Teacher QR',
          instructions: "Align the teacher's QR code within the frame to begin an inspection.",
        ),
      ),
    );
    if (code == null || !mounted) return;

    setState(() => _scanning = true);
    try {
      final teacher = await _inspectionService.lookupTeacherByQr(code);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => TeacherPreviewScreen(teacher: teacher, teacherQr: code)),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.missing),
      );
    } finally {
      if (mounted) setState(() => _scanning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<InboxController>().unread;

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      body: IndexedStack(index: _index, children: _pages),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      floatingActionButton: _CenterScanButton(loading: _scanning, onTap: _startInspectionFlow),
      bottomNavigationBar: _BottomBar(
        index: _index,
        unread: unread,
        onSelect: _onSelectTab,
      ),
    );
  }
}

class _CenterScanButton extends StatelessWidget {
  final bool loading;
  final VoidCallback onTap;

  const _CenterScanButton({required this.loading, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 6),
      height: 64,
      width: 64,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.4),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: FloatingActionButton(
        onPressed: loading ? null : onTap,
        backgroundColor: AppColors.primary,
        elevation: 0,
        shape: const CircleBorder(),
        child: loading
            ? const SizedBox(
                height: 24, width: 24,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
              )
            : const Icon(Icons.qr_code_scanner, color: Colors.white, size: 30),
      ),
    );
  }
}

class _BottomBar extends StatelessWidget {
  final int index;
  final int unread;
  final ValueChanged<int> onSelect;

  const _BottomBar({required this.index, required this.unread, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return BottomAppBar(
      color: AppColors.card,
      elevation: 0,
      shape: const CircularNotchedRectangle(),
      notchMargin: 8,
      height: 64,
      padding: EdgeInsets.zero,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: AppColors.border)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _NavItem(icon: Icons.home_rounded, label: 'Home', active: index == 0, onTap: () => onSelect(0)),
            _NavItem(icon: Icons.mail_rounded, label: 'Inbox', active: index == 1, badge: unread, onTap: () => onSelect(1)),
            const SizedBox(width: 64), // gap for the docked FAB
            _NavItem(icon: Icons.receipt_long_rounded, label: 'Records', active: index == 2, onTap: () => onSelect(2)),
            _NavItem(icon: Icons.person_rounded, label: 'Profile', active: index == 3, onTap: () => onSelect(3)),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;
  final int badge;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon,
    required this.label,
    required this.active,
    required this.onTap,
    this.badge = 0,
  });

  @override
  Widget build(BuildContext context) {
    final color = active ? AppColors.primary : AppColors.textSecondary;
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(icon, color: color, size: 24),
                if (badge > 0)
                  Positioned(
                    right: -6,
                    top: -4,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                      constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.white, width: 1.5),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        badge > 9 ? '9+' : '$badge',
                        style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(color: color, fontSize: 11, fontWeight: active ? FontWeight.w600 : FontWeight.w500),
            ),
          ],
        ),
      ),
    );
  }
}
