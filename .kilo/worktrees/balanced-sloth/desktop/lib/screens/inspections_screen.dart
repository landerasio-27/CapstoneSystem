import 'package:flutter/material.dart';
import '../services/api_client.dart';

class InspectionsScreen extends StatefulWidget {
  const InspectionsScreen({super.key});

  @override
  State<InspectionsScreen> createState() => _InspectionsScreenState();
}

class _InspectionsScreenState extends State<InspectionsScreen> {
  final _api = ApiClient();
  late Future<List<Map<String, dynamic>>> _future;
  String? _status;

  static const _statuses = ['upcoming', 'pending', 'in_progress', 'completed', 'cancelled'];

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final q = _status == null ? '' : '?status=$_status';
    final data = await _api.get('/admin/inspections$q');
    return ((data as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  void _reload() => setState(() => _future = _load());

  void _snack(String m, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(m), backgroundColor: error ? Colors.red.shade700 : null));
  }

  Future<void> _schedule() async {
    final ok = await showDialog<bool>(context: context, builder: (_) => _ScheduleDialog(api: _api));
    if (ok == true) {
      _reload();
      _snack('Inspection task(s) created.');
    }
  }

  Future<void> _openDetail(Map<String, dynamic> row) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _InspectionDetailDialog(api: _api, id: row['id'] as int),
    );
    if (changed == true) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              SizedBox(
                width: 200,
                child: DropdownButtonFormField<String?>(
                  initialValue: _status,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Status', isDense: true),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All statuses')),
                    ..._statuses.map((s) => DropdownMenuItem(value: s, child: Text(_label(s)))),
                  ],
                  onChanged: (v) {
                    setState(() => _status = v);
                    _reload();
                  },
                ),
              ),
              const Spacer(),
              IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
              const SizedBox(width: 8),
              FilledButton.icon(
                onPressed: _schedule,
                icon: const Icon(Icons.add),
                label: const Text('Schedule Inspection'),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Expanded(
            child: Card(
              child: FutureBuilder<List<Map<String, dynamic>>>(
                future: _future,
                builder: (context, snap) {
                  if (snap.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snap.hasError) return Center(child: Text(snap.error.toString()));
                  final rows = snap.data ?? const [];
                  if (rows.isEmpty) {
                    return Center(
                        child: Text('No inspections.', style: TextStyle(color: Colors.grey.shade500)));
                  }
                  return Scrollbar(
                    child: SingleChildScrollView(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: DataTable(
                          headingRowColor: WidgetStatePropertyAll(Colors.grey.shade50),
                          columns: const [
                            DataColumn(label: Text('Reference')),
                            DataColumn(label: Text('Teacher')),
                            DataColumn(label: Text('Room')),
                            DataColumn(label: Text('Assigned')),
                            DataColumn(label: Text('Status')),
                            DataColumn(label: Text('Progress')),
                            DataColumn(label: Text('Scheduled')),
                            DataColumn(label: Text('')),
                          ],
                          rows: [
                            for (final r in rows)
                              DataRow(cells: [
                                DataCell(Row(children: [
                                  if (r['is_urgent'] == true)
                                    const Padding(
                                      padding: EdgeInsets.only(right: 4),
                                      child: Icon(Icons.priority_high, color: Colors.red, size: 16),
                                    ),
                                  Text('${r['reference']}'),
                                ])),
                                DataCell(Text('${r['teacher'] ?? '—'}')),
                                DataCell(Text('${r['room'] ?? '—'}')),
                                DataCell(Text('${r['assigned_staff'] ?? r['inspector'] ?? '—'}')),
                                DataCell(_statusChip('${r['status']}')),
                                DataCell(Text('${r['progress'] ?? ''}')),
                                DataCell(Text('${r['scheduled_at'] ?? 'Now'}')),
                                DataCell(IconButton(
                                  icon: const Icon(Icons.visibility_outlined, size: 18),
                                  onPressed: () => _openDetail(r),
                                )),
                              ]),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  static String _label(String s) =>
      s.replaceAll('_', ' ').split(' ').map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}').join(' ');

  Widget _statusChip(String s) {
    final color = switch (s) {
      'completed' => Colors.green,
      'in_progress' => Colors.amber.shade800,
      'pending' => Colors.blue,
      'upcoming' => Colors.grey,
      'overdue' => Colors.deepOrange,
      'cancelled' => Colors.red,
      _ => Colors.grey,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
      child: Text(_label(s), style: TextStyle(fontSize: 12, color: color)),
    );
  }
}

/// Scheduling form.
class _ScheduleDialog extends StatefulWidget {
  final ApiClient api;
  const _ScheduleDialog({required this.api});

  @override
  State<_ScheduleDialog> createState() => _ScheduleDialogState();
}

class _ScheduleDialogState extends State<_ScheduleDialog> {
  late final Future<void> _ready;
  List<Map<String, dynamic>> _teachers = [];
  List<Map<String, dynamic>> _rooms = [];
  List<Map<String, dynamic>> _staff = [];

  String _scope = 'teacher';
  Object? _teacherId;
  Object? _roomId;
  Object? _staffId;
  String _timing = 'now';
  DateTime? _scheduledAt;
  bool _urgent = false;
  final _notes = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _ready = _load();
  }

  Future<void> _load() async {
    final data = await widget.api.get('/admin/inspections/options') as Map<String, dynamic>;
    _teachers = ((data['teachers'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
    _rooms = ((data['rooms'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
    _staff = ((data['staff'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final body = <String, dynamic>{
        'scope': _scope,
        'timing': _timing,
        'is_urgent': _urgent,
        'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
        'assigned_staff_id': _staffId,
      };
      if (_scope == 'teacher') body['teacher_ids'] = _teacherId == null ? [] : [_teacherId];
      if (_scope == 'room') body['room_id'] = _roomId;
      if (_timing == 'schedule') {
        final d = _scheduledAt ?? DateTime.now().add(const Duration(hours: 1));
        body['scheduled_at'] =
            '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}:00';
      }
      await widget.api.post('/admin/inspections', body);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Unexpected error: $e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickSchedule() async {
    final now = DateTime.now();
    final date = await showDatePicker(
        context: context, initialDate: now, firstDate: now, lastDate: DateTime(now.year + 1));
    if (date == null || !mounted) return;
    final time = await showTimePicker(context: context, initialTime: TimeOfDay.now());
    if (time == null) return;
    setState(() => _scheduledAt = DateTime(date.year, date.month, date.day, time.hour, time.minute));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Schedule Inspection'),
      content: SizedBox(
        width: 460,
        child: FutureBuilder<void>(
          future: _ready,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const SizedBox(height: 160, child: Center(child: CircularProgressIndicator()));
            }
            return SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: _scope,
                    decoration: const InputDecoration(labelText: 'Scope'),
                    items: const [
                      DropdownMenuItem(value: 'teacher', child: Text('One teacher')),
                      DropdownMenuItem(value: 'room', child: Text('A room')),
                      DropdownMenuItem(value: 'all', child: Text('All teachers')),
                    ],
                    onChanged: (v) => setState(() => _scope = v ?? 'teacher'),
                  ),
                  const SizedBox(height: 12),
                  if (_scope == 'teacher')
                    _dropdown('Teacher', _teachers, _teacherId, (v) => setState(() => _teacherId = v)),
                  if (_scope == 'room')
                    _dropdown('Room', _rooms, _roomId, (v) => setState(() => _roomId = v)),
                  const SizedBox(height: 12),
                  _dropdown('Assign to staff (optional)', _staff, _staffId, (v) => setState(() => _staffId = v),
                      nullable: true),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _timing,
                    decoration: const InputDecoration(labelText: 'Timing'),
                    items: const [
                      DropdownMenuItem(value: 'now', child: Text('Start now')),
                      DropdownMenuItem(value: 'schedule', child: Text('Schedule for later')),
                    ],
                    onChanged: (v) => setState(() => _timing = v ?? 'now'),
                  ),
                  if (_timing == 'schedule')
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Scheduled at'),
                        child: Row(children: [
                          Expanded(
                            child: Text(_scheduledAt == null
                                ? 'Not set'
                                : _scheduledAt.toString().substring(0, 16)),
                          ),
                          IconButton(
                              icon: const Icon(Icons.event, size: 18), onPressed: _pickSchedule),
                        ]),
                      ),
                    ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Mark as urgent'),
                    value: _urgent,
                    onChanged: (v) => setState(() => _urgent = v),
                  ),
                  TextField(
                    controller: _notes,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'Notes (optional)'),
                  ),
                  if (_error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(_error!, style: TextStyle(color: Colors.red.shade700)),
                    ),
                ],
              ),
            );
          },
        ),
      ),
      actions: [
        TextButton(onPressed: _saving ? null : () => Navigator.pop(context, false), child: const Text('Cancel')),
        FilledButton(
          onPressed: _saving ? null : _submit,
          child: _saving
              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Create'),
        ),
      ],
    );
  }

  Widget _dropdown(String label, List<Map<String, dynamic>> items, Object? value,
      ValueChanged<Object?> onChanged,
      {bool nullable = false}) {
    return DropdownButtonFormField<Object?>(
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        if (nullable) const DropdownMenuItem(value: null, child: Text('— None —')),
        ...items.map((i) => DropdownMenuItem(value: i['id'], child: Text('${i['label']}'))),
      ],
      onChanged: onChanged,
    );
  }
}

/// Inspection detail + cancel.
class _InspectionDetailDialog extends StatefulWidget {
  final ApiClient api;
  final int id;
  const _InspectionDetailDialog({required this.api, required this.id});

  @override
  State<_InspectionDetailDialog> createState() => _InspectionDetailDialogState();
}

class _InspectionDetailDialogState extends State<_InspectionDetailDialog> {
  late Future<dynamic> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = widget.api.get('/admin/inspections/${widget.id}');
  }

  Future<void> _cancel() async {
    setState(() => _busy = true);
    try {
      await widget.api.post('/admin/inspections/${widget.id}/cancel');
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _busy = false);
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: Colors.red.shade700));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Inspection'),
      content: SizedBox(
        width: 540,
        child: FutureBuilder<dynamic>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const SizedBox(height: 160, child: Center(child: CircularProgressIndicator()));
            }
            if (snap.hasError) return Text(snap.error.toString());
            final d = (snap.data as Map).cast<String, dynamic>();
            final items = ((d['items'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  _kv('Reference', d['reference']),
                  _kv('Teacher', d['teacher']),
                  _kv('Room', d['room']),
                  _kv('Assigned', d['assigned_staff'] ?? d['inspector']),
                  _kv('Status', d['status']),
                  _kv('Progress', '${d['verified_count'] ?? 0}/${d['total_items'] ?? 0}'),
                  _kv('Scheduled', d['scheduled_at'] ?? 'Now'),
                  if ((d['notes'] ?? '').toString().isNotEmpty) _kv('Notes', d['notes']),
                  if (items.isNotEmpty) ...[
                    const Divider(),
                    const Text('Items', style: TextStyle(fontWeight: FontWeight.w600)),
                    for (final it in items)
                      ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text('${it['equipment'] ?? '—'} (${it['item_code'] ?? '—'})'),
                        trailing: Text('${it['status'] ?? ''}'),
                      ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Close')),
        FutureBuilder<dynamic>(
          future: _future,
          builder: (context, snap) {
            final status = snap.hasData ? (snap.data as Map)['status'] : null;
            final canCancel = status != null && status != 'completed' && status != 'cancelled';
            if (!canCancel) return const SizedBox.shrink();
            return FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: Colors.red),
              onPressed: _busy ? null : _cancel,
              icon: const Icon(Icons.cancel, size: 18),
              label: const Text('Cancel inspection'),
            );
          },
        ),
      ],
    );
  }

  Widget _kv(String k, Object? v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 100, child: Text(k, style: const TextStyle(color: Colors.grey))),
          Expanded(child: Text('${v ?? '—'}')),
        ]),
      );
}
