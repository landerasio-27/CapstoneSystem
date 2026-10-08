import 'package:flutter/material.dart';
import '../models/teacher.dart';
import '../services/inspection_service.dart';
import '../theme/app_theme.dart';
import 'inspection_screen.dart';

class TeacherPreviewScreen extends StatefulWidget {
  final Teacher teacher;
  final String teacherQr;

  const TeacherPreviewScreen({super.key, required this.teacher, required this.teacherQr});

  @override
  State<TeacherPreviewScreen> createState() => _TeacherPreviewScreenState();
}

class _TeacherPreviewScreenState extends State<TeacherPreviewScreen> {
  final _service = InspectionService();
  bool _starting = false;

  Future<void> _start() async {
    setState(() => _starting = true);
    try {
      final inspection = await _service.startInspection(widget.teacherQr);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => InspectionScreen(inspection: inspection)),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.missing),
      );
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final teacher = widget.teacher;

    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: Text('Teacher Found'), backgroundColor: AppColors.surfaceTint),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Teacher identity card
                  AppCard(
                    padding: const EdgeInsets.all(20),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 30,
                          backgroundColor: AppColors.primary.withValues(alpha: 0.12),
                          child: Text(_initials(teacher.fullName),
                              style: TextStyle(color: AppColors.primary, fontSize: 20, fontWeight: FontWeight.w700)),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(teacher.fullName,
                                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                              const SizedBox(height: 2),
                              Text(teacher.employeeId, style: TextStyle(color: AppColors.textSecondary)),
                              if (teacher.department != null) ...[
                                const SizedBox(height: 2),
                                Text(teacher.department!, style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),

                  if (teacher.room != null)
                    AppCard(
                      child: Row(
                        children: [
                          Icon(Icons.meeting_room_outlined, color: AppColors.primary),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text('${teacher.room!.code} — ${teacher.room!.name}',
                                style: TextStyle(fontWeight: FontWeight.w500, color: AppColors.textPrimary)),
                          ),
                        ],
                      ),
                    ),
                  const SizedBox(height: 20),

                  Row(
                    children: [
                      Text('Assigned Equipment',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text('${teacher.equipment.length}',
                            style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 12)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  if (teacher.equipment.isEmpty)
                    AppCard(
                      child: Row(
                        children: [
                          Icon(Icons.info_outline, color: AppColors.textSecondary),
                          SizedBox(width: 12),
                          Text('No equipment assigned.', style: TextStyle(color: AppColors.textSecondary)),
                        ],
                      ),
                    )
                  else
                    ...teacher.equipment.map((item) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: AppCard(
                            child: Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: AppColors.surfaceTint,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Icon(Icons.inventory_2_outlined, color: AppColors.primary, size: 20),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(item.name,
                                          style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                                      Text(item.itemCode, style: TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                                    ],
                                  ),
                                ),
                                StatusChip(status: item.status),
                              ],
                            ),
                          ),
                        )),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton.icon(
                onPressed: _starting ? null : _start,
                icon: _starting
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.play_arrow_rounded),
                label: Text(_starting ? 'Starting…' : 'Start Inspection'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
