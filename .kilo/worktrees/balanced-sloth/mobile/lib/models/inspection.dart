class InspectionItem {
  final int id;
  final int? equipmentId;
  final String? name;
  final String? itemCode;
  final String status;

  InspectionItem({
    required this.id,
    this.equipmentId,
    this.name,
    this.itemCode,
    required this.status,
  });

  factory InspectionItem.fromJson(Map<String, dynamic> json) => InspectionItem(
        id: json['id'],
        equipmentId: json['equipment_id'],
        name: json['name'],
        itemCode: json['item_code'],
        status: json['status'] ?? 'pending',
      );
}

class Inspection {
  final int id;
  final String status;
  final String teacherName;
  final String? roomName;
  final String? roomCode;
  final int totalItems;
  final int verifiedCount;
  final int missingCount;
  final int misplacedCount;
  final String progress;
  final bool isUrgent;
  final DateTime? scheduledAt;
  final String? notes;
  final bool available;
  final List<InspectionItem> items;

  Inspection({
    required this.id,
    required this.status,
    required this.teacherName,
    this.roomName,
    this.roomCode,
    required this.totalItems,
    required this.verifiedCount,
    required this.missingCount,
    required this.misplacedCount,
    required this.progress,
    this.isUrgent = false,
    this.scheduledAt,
    this.notes,
    this.available = true,
    required this.items,
  });

  factory Inspection.fromJson(Map<String, dynamic> json) => Inspection(
        id: json['id'],
        status: json['status'] ?? 'in_progress',
        teacherName: json['teacher']?['full_name'] ?? '',
        roomName: json['room']?['name'],
        roomCode: json['room']?['code'],
        totalItems: json['total_items'] ?? 0,
        verifiedCount: json['verified_count'] ?? 0,
        missingCount: json['missing_count'] ?? 0,
        misplacedCount: json['misplaced_count'] ?? 0,
        progress: json['progress'] ?? '0/0',
        isUrgent: json['is_urgent'] ?? false,
        scheduledAt: json['scheduled_at'] != null ? DateTime.tryParse(json['scheduled_at']) : null,
        notes: json['notes'],
        available: json['available'] ?? true,
        items: (json['items'] as List<dynamic>? ?? [])
            .map((e) => InspectionItem.fromJson(e))
            .toList(),
      );
}

/// Summary row for the pending/upcoming/completed lists (GET /inspections/assigned).
class AssignedInspection {
  final int id;
  final String teacher;
  final int? teacherId;
  final String? room;
  final int itemCount;
  final DateTime? scheduledAt;
  final String status;
  final bool isUrgent;
  final bool available;
  final String? notes;
  final String progress;

  AssignedInspection({
    required this.id,
    required this.teacher,
    this.teacherId,
    this.room,
    required this.itemCount,
    this.scheduledAt,
    required this.status,
    required this.isUrgent,
    required this.available,
    this.notes,
    required this.progress,
  });

  factory AssignedInspection.fromJson(Map<String, dynamic> json) => AssignedInspection(
        id: json['id'],
        teacher: json['teacher'] ?? '',
        teacherId: json['teacher_id'],
        room: json['room'],
        itemCount: json['item_count'] ?? 0,
        scheduledAt: json['scheduled_at'] != null ? DateTime.tryParse(json['scheduled_at']) : null,
        status: json['status'] ?? 'pending',
        isUrgent: json['is_urgent'] ?? false,
        available: json['available'] ?? false,
        notes: json['notes'],
        progress: json['progress'] ?? '0/0',
      );
}

class InspectionHistoryEntry {
  final int id;
  final String teacher;
  final String? room;
  final String status;
  final String progress;
  final int missingCount;
  final int misplacedCount;
  final DateTime startedAt;
  final DateTime? submittedAt;

  InspectionHistoryEntry({
    required this.id,
    required this.teacher,
    this.room,
    required this.status,
    required this.progress,
    required this.missingCount,
    required this.misplacedCount,
    required this.startedAt,
    this.submittedAt,
  });

  factory InspectionHistoryEntry.fromJson(Map<String, dynamic> json) => InspectionHistoryEntry(
        id: json['id'],
        teacher: json['teacher'] ?? '',
        room: json['room'],
        status: json['status'] ?? '',
        progress: json['progress'] ?? '0/0',
        missingCount: json['missing_count'] ?? 0,
        misplacedCount: json['misplaced_count'] ?? 0,
        startedAt: DateTime.parse(json['started_at']),
        submittedAt: json['submitted_at'] != null ? DateTime.parse(json['submitted_at']) : null,
      );
}
