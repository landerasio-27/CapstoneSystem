import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/inspection.dart';
import '../services/inspection_service.dart';
import '../theme/app_theme.dart';
import 'inspection_screen.dart';

/// Shows a scheduled/assigned inspection's details (teacher, room, equipment,
/// admin notes) with a Start button that is only enabled when the inspection is
/// available. Starting claims it (race-safe) and opens the continuous scanner.
class InspectionDetailScreen extends StatefulWidget {
  final int inspectionId;

  const InspectionDetailScreen({super.key, required this.inspectionId});

  @override
  State<InspectionDetailScreen> createState() => _InspectionDetailScreenState();
}

class _InspectionDetailScreenState extends State<InspectionDetailScreen> {
  final _service = InspectionService();
  late Future<Inspection> _future;
  bool _starting = false;

  @override
  void initState() {
    super.initState();
    _future = _service.getInspection(widget.inspectionId);
  }

  Future<void> _refresh() async {
    setState(() => _future = _service.getInspection(widget.inspectionId));
    await _future;
  }

  Future<void> _start(Inspection inspection) async {
    setState(() => _starting = true);
    try {
      // Resume if it's already mine (in progress); otherwise claim it.
      final started = inspection.status == 'in_progress'
          ? inspection
          : await _service.claimInspection(inspection.id);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => InspectionScreen(inspection: started)),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.missing),
      );
      _refresh(); // it may have been claimed by someone else
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  IconData _statusIcon(String s) => switch (s) {
        'verified' => Icons.check_circle_rounded,
        'missing' => Icons.cancel_rounded,
        'misplaced' => Icons.warning_rounded,
        _ => Icons.radio_button_unchecked_rounded,
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: Text('Inspection'), backgroundColor: AppColors.surfaceTint),
      body: SafeArea(
        top: false,
        child: FutureBuilder<Inspection>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return Center(child: CircularProgressIndicator(color: AppColors.primary));
            }
            if (snapshot.hasError) {
              return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(snapshot.error.toString())));
            }
            final inspection = snapshot.data!;
            return _content(inspection);
          },
        ),
      ),
    );
  }

  Widget _content(Inspection inspection) {
    final scheduled = inspection.scheduledAt?.toLocal();
    final dateFormat = DateFormat('MMM d, y • h:mm a');

    return RefreshIndicator(
      onRefresh: _refresh,
      color: AppColors.primary,
      child: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                AppCard(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(inspection.teacherName,
                                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                          ),
                          if (inspection.isUrgent)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(color: AppColors.missing, borderRadius: BorderRadius.circular(20)),
                              child: const Text('URGENT', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)),
                            ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(children: [
                        Icon(Icons.meeting_room_outlined, size: 18, color: AppColors.textSecondary),
                        const SizedBox(width: 6),
                        Text(inspection.roomName ?? inspection.roomCode ?? 'No room', style: TextStyle(color: AppColors.textSecondary)),
                        const SizedBox(width: 16),
                        Icon(Icons.inventory_2_outlined, size: 18, color: AppColors.textSecondary),
                        const SizedBox(width: 6),
                        Text('${inspection.totalItems} items', style: TextStyle(color: AppColors.textSecondary)),
                      ]),
                      if (scheduled != null) ...[
                        const SizedBox(height: 6),
                        Row(children: [
                          Icon(Icons.schedule_rounded, size: 18, color: AppColors.textSecondary),
                          const SizedBox(width: 6),
                          Text(dateFormat.format(scheduled), style: TextStyle(color: AppColors.textSecondary)),
                        ]),
                      ],
                    ],
                  ),
                ),
                if (inspection.notes != null && inspection.notes!.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  AppCard(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.sticky_note_2_outlined, color: AppColors.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Admin Notes', style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                              const SizedBox(height: 4),
                              Text(inspection.notes!, style: TextStyle(color: AppColors.textSecondary)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                Text('Equipment to inspect', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                const SizedBox(height: 8),
                ...inspection.items.map((item) {
                  final (bg, fg) = AppTheme.statusColors(item.status);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
                            child: Icon(_statusIcon(item.status), color: fg, size: 20),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(item.name ?? 'Unknown item',
                                    style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                                Text(item.itemCode ?? '', style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                              ],
                            ),
                          ),
                          if (item.status != 'pending') StatusChip(status: item.status),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
          ),
          _actionBar(inspection, scheduled, dateFormat),
        ],
      ),
    );
  }

  Widget _actionBar(Inspection inspection, DateTime? scheduled, DateFormat fmt) {
    final canStart = inspection.status == 'pending' && inspection.available;
    final inProgress = inspection.status == 'in_progress';
    final completed = inspection.status == 'completed';

    if (completed) {
      return Padding(
        padding: const EdgeInsets.all(16),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.verified.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(14)),
          alignment: Alignment.center,
          child: Text('Completed — verified ${inspection.progress}',
              style: TextStyle(color: AppColors.verified, fontWeight: FontWeight.w700)),
        ),
      );
    }

    final enabled = (canStart || inProgress) && !_starting;
    final label = inProgress
        ? 'Continue Inspection'
        : (canStart ? 'Start Inspection' : 'Available on ${scheduled != null ? fmt.format(scheduled) : "the scheduled time"}');

    return Padding(
      padding: const EdgeInsets.all(16),
      child: FilledButton.icon(
        onPressed: enabled ? () => _start(inspection) : null,
        icon: _starting
            ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
            : Icon(inProgress ? Icons.play_arrow_rounded : (canStart ? Icons.qr_code_scanner_rounded : Icons.lock_clock_rounded)),
        label: Text(label),
      ),
    );
  }
}
