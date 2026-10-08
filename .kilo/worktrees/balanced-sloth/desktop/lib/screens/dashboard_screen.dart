import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme/app_theme.dart';

/// Live admin dashboard — stat cards + recent activity, from GET /api/admin/dashboard.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _api = ApiClient();
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final data = await _api.get('/admin/dashboard');
    return data as Map<String, dynamic>;
  }

  void _refresh() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return _ErrorView(message: snap.error.toString(), onRetry: _refresh);
        }
        final data = snap.data!;
        final stats = (data['stats'] as Map).cast<String, dynamic>();
        final activity = (data['recent_activity'] as List).cast<dynamic>();

        return RefreshIndicator(
          onRefresh: () async => _refresh(),
          child: ListView(
            padding: const EdgeInsets.all(24),
            children: [
              _statGrid(stats),
              const SizedBox(height: 24),
              _recentActivity(activity),
            ],
          ),
        );
      },
    );
  }

  Widget _statGrid(Map<String, dynamic> s) {
    final cards = [
      _Stat('Total Equipment', '${s['total_equipment'] ?? 0}', Icons.inventory_2_outlined,
          AppTheme.brand),
      _Stat('Total Supplies', '${s['total_supplies'] ?? 0}', Icons.inventory_outlined,
          Colors.blue),
      _Stat(
          'Low Stock Items',
          '${(s['low_stock_supplies'] ?? 0) + (s['low_stock_equipment'] ?? 0)}',
          Icons.warning_amber_rounded,
          Colors.amber.shade800),
      _Stat('Missing Equipment', '${s['missing_equipment'] ?? 0}', Icons.report_gmailerrorred,
          Colors.red),
      _Stat('Misplaced Equipment', '${s['misplaced_equipment'] ?? 0}', Icons.wrong_location_outlined,
          Colors.orange),
      _Stat('Inspections Today', '${s['inspections_today'] ?? 0}', Icons.today, Colors.teal),
      _Stat('Draft Documents', '${s['documents_draft'] ?? 0}', Icons.description_outlined,
          Colors.indigo),
      _Stat('Inspection Completion', '${s['inspection_completion_rate'] ?? 0}%',
          Icons.donut_large, Colors.purple),
    ];

    return LayoutBuilder(builder: (context, c) {
      final cols = c.maxWidth > 1100 ? 4 : (c.maxWidth > 720 ? 3 : 2);
      return GridView.count(
        crossAxisCount: cols,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 16,
        crossAxisSpacing: 16,
        childAspectRatio: 2.1,
        children: cards.map((c) => _StatCard(stat: c)).toList(),
      );
    });
  }

  Widget _recentActivity(List<dynamic> activity) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Recent Activity',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            const SizedBox(height: 12),
            if (activity.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Center(
                    child: Text('No activity recorded yet.',
                        style: TextStyle(color: Colors.grey.shade500))),
              )
            else
              ...activity.map((raw) {
                final log = (raw as Map).cast<String, dynamic>();
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      CircleAvatar(
                        radius: 16,
                        backgroundColor: AppTheme.brand.withValues(alpha: 0.10),
                        child: const Icon(Icons.history, size: 16, color: AppTheme.brand),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${log['description'] ?? ''}'),
                            Text(
                              '${log['user'] ?? 'System'} · ${_ucFirst('${log['module'] ?? ''}')}',
                              style: TextStyle(color: Colors.grey.shade500, fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                      Text('${log['created_at_human'] ?? ''}',
                          style: TextStyle(color: Colors.grey.shade400, fontSize: 12)),
                    ],
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }

  String _ucFirst(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);
}

class _Stat {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  _Stat(this.label, this.value, this.icon, this.color);
}

class _StatCard extends StatelessWidget {
  final _Stat stat;
  const _StatCard({required this.stat});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: stat.color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(stat.icon, color: stat.color, size: 22),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(stat.value,
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold)),
                Text(stat.label, style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 16),
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade700)),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
