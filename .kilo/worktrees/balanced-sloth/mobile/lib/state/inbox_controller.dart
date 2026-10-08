import 'package:flutter/foundation.dart';
import '../services/inspection_service.dart';

/// Tracks the unread announcement/notification count for the Inbox badge.
class InboxController extends ChangeNotifier {
  final _service = InspectionService();

  int _unread = 0;
  int get unread => _unread;

  Future<void> refresh() async {
    try {
      final list = await _service.notifications();
      _unread = list.where((n) => !n.isRead).length;
      notifyListeners();
    } catch (_) {
      // Network hiccup — leave the current count as-is.
    }
  }

  Future<void> markAllRead() async {
    try {
      await _service.markAllRead();
    } catch (_) {
      // Even if the server call fails, clear locally so the badge doesn't stick.
    }
    _unread = 0;
    notifyListeners();
  }

  void reset() {
    _unread = 0;
    notifyListeners();
  }
}
