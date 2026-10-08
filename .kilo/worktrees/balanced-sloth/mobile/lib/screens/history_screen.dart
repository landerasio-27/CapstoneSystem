import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/inspection.dart';
import '../services/inspection_service.dart';
import '../theme/app_theme.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  final _service = InspectionService();
  late Future<List<InspectionHistoryEntry>> _future;

  @override
  void initState() {
    super.initState();
    _future = _service.history();
  }

  Future<void> _refresh() async {
    setState(() => _future = _service.history());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('MMM d, y • h:mm a');

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: Text('Inspection Records'), backgroundColor: AppColors.surfaceTint),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: AppColors.primary,
          child: FutureBuilder<List<InspectionHistoryEntry>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return Center(child: CircularProgressIndicator(color: AppColors.primary));
              }
              if (snapshot.hasError) {
                return _message(snapshot.error.toString());
              }

              final entries = snapshot.data ?? [];
              if (entries.isEmpty) {
                return _message('No inspection records yet.', icon: Icons.receipt_long_rounded);
              }

              return ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                itemCount: entries.length,
                itemBuilder: (context, index) {
                  final entry = entries[index];
                  final completed = entry.status == 'completed';
                  final color = completed ? AppColors.verified : AppColors.misplaced;

                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: color.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(completed ? Icons.check_circle_rounded : Icons.hourglass_top_rounded, color: color),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(entry.teacher,
                                        style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                                    Text('${entry.room ?? "—"} • ${dateFormat.format((entry.submittedAt ?? entry.startedAt).toLocal())}',
                                        style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                  ],
                                ),
                              ),
                              StatusChip(status: completed ? 'verified' : 'pending'),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              _stat('Verified', entry.progress, AppColors.verified),
                              _stat('Missing', '${entry.missingCount}', AppColors.missing),
                              _stat('Misplaced', '${entry.misplacedCount}', AppColors.misplaced),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                },
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _stat(String label, String value, Color color) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: TextStyle(fontWeight: FontWeight.w700, color: color)),
          Text(label, style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
        ],
      ),
    );
  }

  Widget _message(String text, {IconData icon = Icons.error_outline}) {
    return ListView(
      children: [
        const SizedBox(height: 120),
        Icon(icon, size: 48, color: AppColors.textSecondary),
        const SizedBox(height: 12),
        Center(child: Text(text, style: TextStyle(color: AppColors.textSecondary))),
      ],
    );
  }
}
