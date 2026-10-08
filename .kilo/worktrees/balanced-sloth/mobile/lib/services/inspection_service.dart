import '../models/teacher.dart';
import '../models/inspection.dart';
import '../models/notification_item.dart';
import 'api_client.dart';

class InspectionService {
  final ApiClient _client = ApiClient();

  Future<Teacher> lookupTeacherByQr(String qr) async {
    final data = await _client.get('/teachers/${Uri.encodeComponent(qr)}');
    return Teacher.fromJson(data);
  }

  Future<Inspection> startInspection(String teacherQr) async {
    final data = await _client.post('/inspections/start', {'teacher_qr': teacherQr});
    return Inspection.fromJson(data);
  }

  /// Returns the scan result: 'verified' | 'misplaced' | 'invalid', plus progress text.
  Future<Map<String, dynamic>> scanEquipment(int inspectionId, String equipmentQr) async {
    final data = await _client.post('/inspections/$inspectionId/scan', {'equipment_qr': equipmentQr});
    return data as Map<String, dynamic>;
  }

  Future<Inspection> submitInspection(int inspectionId) async {
    final data = await _client.post('/inspections/$inspectionId/submit', {});
    return Inspection.fromJson(data);
  }

  Future<List<InspectionHistoryEntry>> history() async {
    final data = await _client.get('/inspections/history');
    final list = data['data'] as List<dynamic>;
    return list.map((e) => InspectionHistoryEntry.fromJson(e)).toList();
  }

  /// Load a single inspection (with items) — used to resume an in-progress
  /// inspection or view a completed one's details.
  Future<Inspection> getInspection(int id) async {
    final data = await _client.get('/inspections/$id');
    return Inspection.fromJson(data);
  }

  /// Admin-scheduled inspections for this staff, by tab: pending|upcoming|completed.
  Future<List<AssignedInspection>> assignedInspections(String status) async {
    final data = await _client.get('/inspections/assigned?status=$status');
    final list = data['data'] as List<dynamic>;
    return list.map((e) => AssignedInspection.fromJson(e)).toList();
  }

  /// Claim + start a scheduled inspection (race-safe on the server).
  Future<Inspection> claimInspection(int id) async {
    final data = await _client.post('/inspections/$id/claim', {});
    return Inspection.fromJson(data);
  }

  Future<List<NotificationItem>> notifications() async {
    final data = await _client.get('/notifications');
    final list = data['data'] as List<dynamic>;
    return list.map((e) => NotificationItem.fromJson(e)).toList();
  }

  Future<void> markAllRead() async {
    await _client.post('/notifications/read-all', {});
  }

  /// Fetch a single announcement (for deep-linking from a tapped push).
  Future<Map<String, dynamic>> getAnnouncement(int id) async {
    final data = await _client.get('/announcements/$id');
    return data as Map<String, dynamic>;
  }

  Future<void> updateFcmToken(String token) async {
    await _client.post('/fcm-token', {'token': token, 'platform': 'android'});
  }

  Future<void> removeFcmToken(String token) async {
    await _client.post('/fcm-token/remove', {'token': token});
  }
}
