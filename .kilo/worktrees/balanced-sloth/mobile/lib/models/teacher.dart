class Room {
  final int id;
  final String code;
  final String name;

  Room({required this.id, required this.code, required this.name});

  factory Room.fromJson(Map<String, dynamic> json) => Room(
        id: json['id'],
        code: json['code'] ?? '',
        name: json['name'] ?? '',
      );
}

class EquipmentSummary {
  final int id;
  final String itemCode;
  final String name;
  final String status;

  EquipmentSummary({
    required this.id,
    required this.itemCode,
    required this.name,
    required this.status,
  });

  factory EquipmentSummary.fromJson(Map<String, dynamic> json) => EquipmentSummary(
        id: json['id'],
        itemCode: json['item_code'] ?? '',
        name: json['name'] ?? '',
        status: json['status'] ?? '',
      );
}

class Teacher {
  final int id;
  final String employeeId;
  final String fullName;
  final String? department;
  final String? position;
  final Room? room;
  final List<EquipmentSummary> equipment;

  Teacher({
    required this.id,
    required this.employeeId,
    required this.fullName,
    this.department,
    this.position,
    this.room,
    required this.equipment,
  });

  factory Teacher.fromJson(Map<String, dynamic> json) => Teacher(
        id: json['id'],
        employeeId: json['employee_id'] ?? '',
        fullName: json['full_name'] ?? '',
        department: json['department'],
        position: json['position'],
        room: json['room'] != null ? Room.fromJson(json['room']) : null,
        equipment: (json['equipment'] as List<dynamic>? ?? [])
            .map((e) => EquipmentSummary.fromJson(e))
            .toList(),
      );
}
