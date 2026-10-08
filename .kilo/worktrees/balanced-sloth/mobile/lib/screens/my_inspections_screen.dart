import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/inspection.dart';
import '../services/inspection_service.dart';
import '../theme/app_theme.dart';
import 'inspection_detail_screen.dart';

/// The dedicated Pending / Upcoming / Completed inspection lists.
class MyInspectionsScreen extends StatelessWidget {
  final int initialTab; // 0 pending, 1 upcoming, 2 completed

  const MyInspectionsScreen({super.key, this.initialTab = 0});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      initialIndex: initialTab,
      child: Scaffold(
        backgroundColor: AppColors.surfaceTint,
        appBar: AppBar(
          backgroundColor: AppColors.surfaceTint,
          title: const Text('My Inspections'),
          bottom: TabBar(
            labelColor: AppColors.primary,
            unselectedLabelColor: AppColors.textSecondary,
            indicatorColor: AppColors.primary,
            tabs: [Tab(text: 'Pending'), Tab(text: 'Upcoming'), Tab(text: 'Completed')],
          ),
        ),
        body: const TabBarView(
          children: [
            _InspectionTab(status: 'pending'),
            _InspectionTab(status: 'upcoming'),
            _InspectionTab(status: 'completed'),
          ],
        ),
      ),
    );
  }
}

class _InspectionTab extends StatefulWidget {
  final String status;
  const _InspectionTab({required this.status});

  @override
  State<_InspectionTab> createState() => _InspectionTabState();
}

class _InspectionTabState extends State<_InspectionTab> with AutomaticKeepAliveClientMixin {
  final _service = InspectionService();
  late Future<List<AssignedInspection>> _future;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _future = _service.assignedInspections(widget.status);
  }

  Future<void> _refresh() async {
    setState(() => _future = _service.assignedInspections(widget.status));
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return RefreshIndicator(
      onRefresh: _refresh,
      color: AppColors.primary,
      child: FutureBuilder<List<AssignedInspection>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          if (snapshot.hasError) {
            return _empty(snapshot.error.toString(), Icons.error_outline);
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) {
            return _empty(
              switch (widget.status) {
                'pending' => 'No pending inspections right now.',
                'upcoming' => 'No upcoming scheduled inspections.',
                _ => 'No completed inspections yet.',
              },
              switch (widget.status) {
                'pending' => Icons.task_alt_rounded,
                'upcoming' => Icons.event_available_rounded,
                _ => Icons.inbox_rounded,
              },
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
            itemCount: items.length,
            itemBuilder: (context, i) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: InspectionCard(
                inspection: items[i],
                onTap: () async {
                  await Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => InspectionDetailScreen(inspectionId: items[i].id)),
                  );
                  if (mounted) _refresh();
                },
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _empty(String text, IconData icon) => ListView(
        children: [
          const SizedBox(height: 120),
          Icon(icon, size: 48, color: AppColors.textSecondary),
          const SizedBox(height: 12),
          Center(child: Text(text, textAlign: TextAlign.center, style: TextStyle(color: AppColors.textSecondary))),
        ],
      );
}

/// Reusable card for an assigned/scheduled inspection (used on the list + Home).
class InspectionCard extends StatelessWidget {
  final AssignedInspection inspection;
  final VoidCallback onTap;

  const InspectionCard({super.key, required this.inspection, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final scheduled = inspection.scheduledAt?.toLocal();
    final dateFormat = DateFormat('MMM d • h:mm a');
    final (bg, fg) = _statusColors(inspection.status);

    return AppCard(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(12)),
            child: Icon(_statusIcon(inspection.status), color: fg),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(inspection.teacher,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                    ),
                    if (inspection.isUrgent) ...[
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(color: AppColors.missing, borderRadius: BorderRadius.circular(6)),
                        child: const Text('URGENT', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 2),
                Text('${inspection.room ?? "—"} • ${inspection.itemCount} items',
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                Text(scheduled != null ? dateFormat.format(scheduled) : 'Available now',
                    style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
              ],
            ),
          ),
          _StatusChip(status: inspection.status),
        ],
      ),
    );
  }

  (Color, Color) _statusColors(String s) => switch (s) {
        'completed' => (AppColors.verified.withValues(alpha: 0.12), AppColors.verified),
        'in_progress' => (
            const Color(0xFF0EA5E9).withValues(alpha: 0.14),
            const Color(0xFF0284C7),
          ),
        'overdue' => (AppColors.missing.withValues(alpha: 0.12), AppColors.missing),
        'upcoming' => (
            AppColors.border.withValues(alpha: 0.6),
            AppColors.textSecondary,
          ),
        _ => (AppColors.misplaced.withValues(alpha: 0.14), AppColors.misplaced), // pending
      };

  IconData _statusIcon(String s) => switch (s) {
        'completed' => Icons.check_circle_rounded,
        'in_progress' => Icons.play_circle_rounded,
        'overdue' => Icons.error_rounded,
        'upcoming' => Icons.schedule_rounded,
        _ => Icons.pending_actions_rounded,
      };
}

class _StatusChip extends StatelessWidget {
  final String status;
  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    final label = switch (status) {
      'in_progress' => 'In Progress',
      'upcoming' => 'Upcoming',
      'completed' => 'Completed',
      'overdue' => 'Overdue',
      _ => 'Pending',
    };
    final color = switch (status) {
      'completed' => AppColors.verified,
      'in_progress' => const Color(0xFF0284C7),
      'overdue' => AppColors.missing,
      'upcoming' => AppColors.textSecondary,
      _ => AppColors.misplaced,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
    );
  }
}
