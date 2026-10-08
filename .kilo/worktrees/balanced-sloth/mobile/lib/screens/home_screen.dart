import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../services/inspection_service.dart';
import '../models/inspection.dart';
import '../theme/app_theme.dart';
import '../state/app_events.dart';
import 'my_inspections_screen.dart';
import 'inspection_detail_screen.dart';

class HomeScreen extends StatefulWidget {
  final Future<void> Function() onStartInspection;

  const HomeScreen({super.key, required this.onStartInspection});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _service = InspectionService();
  late Future<List<InspectionHistoryEntry>> _future;
  late Future<List<AssignedInspection>> _pendingFuture;

  @override
  void initState() {
    super.initState();
    _future = _service.history();
    _pendingFuture = _service.assignedInspections('pending');
    // Refresh the pending list when an inspection push arrives.
    notificationsChanged.addListener(_reloadPending);
  }

  @override
  void dispose() {
    notificationsChanged.removeListener(_reloadPending);
    super.dispose();
  }

  void _reloadPending() {
    if (mounted) setState(() => _pendingFuture = _service.assignedInspections('pending'));
  }

  Future<void> _refresh() async {
    setState(() {
      _future = _service.history();
      _pendingFuture = _service.assignedInspections('pending');
    });
    await Future.wait([_future, _pendingFuture]);
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final name = auth.user?.name ?? '';
    final firstName = name.isEmpty ? 'Inspector' : name.split(' ').first;

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: AppColors.primary,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
            children: [
              // Greeting header
              Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Good day,', style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
                        Text(firstName,
                            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                      ],
                    ),
                  ),
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                    child: Text(
                      _initials(name),
                      style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // Summary cards from inspection history
              FutureBuilder<List<InspectionHistoryEntry>>(
                future: _future,
                builder: (context, snapshot) {
                  final entries = snapshot.data ?? [];
                  final assigned = entries.length;
                  final completed = entries.where((e) => e.status == 'completed').length;
                  final pending = entries.where((e) => e.status != 'completed').length;
                  final loading = snapshot.connectionState == ConnectionState.waiting;

                  return Row(
                    children: [
                      _summaryCard('Inspections', assigned, Icons.assignment_rounded, AppColors.primary, loading, 0),
                      const SizedBox(width: 12),
                      _summaryCard('Pending', pending, Icons.pending_actions_rounded, AppColors.misplaced, loading, 0),
                      const SizedBox(width: 12),
                      _summaryCard('Completed', completed, Icons.task_alt_rounded, AppColors.secondary, loading, 2),
                    ],
                  );
                },
              ),
              const SizedBox(height: 20),

              // Pending Inspections (admin-scheduled tasks assigned to / open for me)
              FutureBuilder<List<AssignedInspection>>(
                future: _pendingFuture,
                builder: (context, snapshot) {
                  final items = snapshot.data ?? [];
                  if (items.isEmpty) return const SizedBox.shrink();
                  final preview = items.take(3).toList();
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Pending Inspections',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                          if (items.length > preview.length)
                            TextButton(
                              onPressed: () => Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const MyInspectionsScreen(initialTab: 0)),
                              ),
                              child: Text('See all (${items.length})'),
                            ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ...preview.map((insp) => Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: InspectionCard(
                              inspection: insp,
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(builder: (_) => InspectionDetailScreen(inspectionId: insp.id)),
                                );
                                if (mounted) _reloadPending();
                              },
                            ),
                          )),
                      const SizedBox(height: 12),
                    ],
                  );
                },
              ),

              // Primary CTA — start an inspection
              AppCard(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppColors.primary.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(Icons.qr_code_scanner, color: AppColors.primary, size: 28),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Start an inspection',
                                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                              SizedBox(height: 2),
                              Text('Scan a teacher QR code to load their assigned equipment.',
                                  style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: widget.onStartInspection,
                      icon: const Icon(Icons.document_scanner_outlined),
                      label: const Text('Scan Teacher QR'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Recent records preview
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Recent Records',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                ],
              ),
              const SizedBox(height: 8),
              FutureBuilder<List<InspectionHistoryEntry>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return Padding(
                      padding: const EdgeInsets.all(24),
                      child: Center(child: CircularProgressIndicator(color: AppColors.primary)),
                    );
                  }
                  final entries = (snapshot.data ?? []).take(4).toList();
                  if (entries.isEmpty) {
                    return AppCard(
                      child: Row(
                        children: [
                          Icon(Icons.inbox_rounded, color: AppColors.textSecondary),
                          const SizedBox(width: 12),
                          Text('No inspections yet.', style: TextStyle(color: AppColors.textSecondary)),
                        ],
                      ),
                    );
                  }
                  return Column(
                    children: entries.map((e) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: AppCard(
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: (e.status == 'completed' ? AppColors.verified : AppColors.misplaced)
                                    .withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                e.status == 'completed' ? Icons.check_circle_rounded : Icons.hourglass_top_rounded,
                                color: e.status == 'completed' ? AppColors.verified : AppColors.misplaced,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(e.teacher,
                                      style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                                  const SizedBox(height: 2),
                                  Text('${e.room ?? "—"} • Verified ${e.progress}',
                                      style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    )).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _summaryCard(String label, int value, IconData icon, Color color, bool loading, int tab) {
    return Expanded(
      child: AppCard(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        onTap: () async {
          await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => MyInspectionsScreen(initialTab: tab)),
          );
          if (mounted) _refresh(); // counts may have changed after resuming/submitting
        },
        child: Column(
          children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 8),
            loading
                ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2))
                : Text('$value',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
            const SizedBox(height: 2),
            Text(label, style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }
}
