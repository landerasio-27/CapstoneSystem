import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/notification_item.dart';
import '../services/inspection_service.dart';
import '../state/app_events.dart';
import '../theme/app_theme.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final _service = InspectionService();
  late Future<List<NotificationItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = _service.notifications();
    notificationsChanged.addListener(_reload);
    pendingAnnouncementId.addListener(_handlePendingAnnouncement);
    // In case a terminated-state push set the id before this screen mounted.
    WidgetsBinding.instance.addPostFrameCallback((_) => _handlePendingAnnouncement());
  }

  @override
  void dispose() {
    notificationsChanged.removeListener(_reload);
    pendingAnnouncementId.removeListener(_handlePendingAnnouncement);
    super.dispose();
  }

  void _reload() {
    if (mounted) setState(() => _future = _service.notifications());
  }

  Future<void> _refresh() async {
    setState(() => _future = _service.notifications());
    await _future;
  }

  Future<void> _handlePendingAnnouncement() async {
    final id = pendingAnnouncementId.value;
    if (id == null || !mounted) return;
    pendingAnnouncementId.value = null; // consume it

    try {
      final a = await _service.getAnnouncement(id);
      if (!mounted) return;
      _showDetail(
        title: a['title'] ?? 'Announcement',
        body: a['body'] ?? '',
        timestamp: a['published_at'] != null ? DateTime.tryParse(a['published_at']) : null,
        priority: a['priority'],
      );
    } catch (_) {
      // If the fetch fails, at least make sure the list is fresh.
      _reload();
    }
  }

  (IconData, Color) _visual(String type) {
    switch (type) {
      case 'inspection_reminder':
        return (Icons.alarm_rounded, AppColors.primary);
      case 'missing_equipment':
        return (Icons.error_outline_rounded, AppColors.missing);
      case 'misplaced_equipment':
        return (Icons.warning_amber_rounded, AppColors.misplaced);
      case 'announcement':
        return (Icons.campaign_rounded, AppColors.primary);
      default:
        return (Icons.notifications_rounded, AppColors.textSecondary);
    }
  }

  void _showDetail({required String title, required String body, DateTime? timestamp, String? priority}) {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      isScrollControlled: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40, height: 4,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
                ),
              ),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(Icons.campaign_rounded, color: AppColors.primary),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(title,
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                  ),
                ],
              ),
              if (priority != null && priority != 'normal') ...[
                const SizedBox(height: 10),
                _PriorityTag(priority: priority),
              ],
              const SizedBox(height: 14),
              Flexible(
                child: SingleChildScrollView(
                  child: Text(body, style: TextStyle(fontSize: 15, height: 1.5, color: AppColors.textPrimary)),
                ),
              ),
              if (timestamp != null) ...[
                const SizedBox(height: 16),
                Text(DateFormat('MMMM d, y • h:mm a').format(timestamp.toLocal()),
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
              ],
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final dateFormat = DateFormat('MMM d • h:mm a');

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: Text('Inbox'), backgroundColor: AppColors.surfaceTint),
      body: SafeArea(
        top: false,
        child: RefreshIndicator(
          onRefresh: _refresh,
          color: AppColors.primary,
          child: FutureBuilder<List<NotificationItem>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return Center(child: CircularProgressIndicator(color: AppColors.primary));
              }
              if (snapshot.hasError) {
                return _message(snapshot.error.toString());
              }

              final items = snapshot.data ?? [];
              if (items.isEmpty) {
                return _message('No notifications yet.', icon: Icons.inbox_rounded);
              }

              return ListView.builder(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  final (icon, color) = _visual(item.type);
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      onTap: () => _showDetail(title: item.title, body: item.body, timestamp: item.createdAt),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(10),
                            decoration: BoxDecoration(
                              color: color.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Icon(icon, color: color, size: 22),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(item.title,
                                          style: TextStyle(
                                            fontWeight: item.isRead ? FontWeight.w500 : FontWeight.w700,
                                            color: AppColors.textPrimary,
                                          )),
                                    ),
                                    if (!item.isRead)
                                      Container(
                                        width: 9, height: 9,
                                        decoration: BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(item.body,
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                                const SizedBox(height: 6),
                                Text(dateFormat.format(item.createdAt.toLocal()),
                                    style: TextStyle(fontSize: 11, color: AppColors.textSecondary)),
                              ],
                            ),
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

class _PriorityTag extends StatelessWidget {
  final String priority;
  const _PriorityTag({required this.priority});

  @override
  Widget build(BuildContext context) {
    final color = priority == 'urgent' ? AppColors.missing : AppColors.misplaced;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(
        priority[0].toUpperCase() + priority.substring(1),
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}
