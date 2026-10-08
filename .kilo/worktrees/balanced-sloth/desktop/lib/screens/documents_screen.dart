import 'dart:io';
import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme/app_theme.dart';

class DocumentsScreen extends StatefulWidget {
  const DocumentsScreen({super.key});

  @override
  State<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends State<DocumentsScreen> {
  final _api = ApiClient();
  final _searchCtrl = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;
  String _search = '';
  String? _type;
  String? _status;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final params = <String, String>{
      if (_search.isNotEmpty) 'search': _search,
      if (_type != null) 'type': _type!,
      if (_status != null) 'status': _status!,
    };
    final qs = params.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&');
    final data = await _api.get(qs.isEmpty ? '/admin/documents' : '/admin/documents?$qs');
    return ((data as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  void _reload() => setState(() => _future = _load());

  void _snack(String m, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(m), backgroundColor: error ? Colors.red.shade700 : null));
  }

  Future<void> _newDocument() async {
    final ok = await showDialog<bool>(context: context, builder: (_) => _NewDocumentDialog(api: _api));
    if (ok == true) {
      _reload();
      _snack('Document created.');
    }
  }

  Future<void> _openDetail(Map<String, dynamic> row) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _DocumentDetailDialog(api: _api, id: row['id'] as int),
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
                width: 240,
                child: TextField(
                  controller: _searchCtrl,
                  decoration: const InputDecoration(
                      hintText: 'Search document no…', prefixIcon: Icon(Icons.search), isDense: true),
                  onSubmitted: (v) {
                    setState(() => _search = v);
                    _reload();
                  },
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 130,
                child: DropdownButtonFormField<String?>(
                  initialValue: _type,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Type', isDense: true),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All')),
                    ...['PAR', 'ICS', 'RIS'].map((t) => DropdownMenuItem(value: t, child: Text(t))),
                  ],
                  onChanged: (v) {
                    setState(() => _type = v);
                    _reload();
                  },
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 150,
                child: DropdownButtonFormField<String?>(
                  initialValue: _status,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Status', isDense: true),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('All')),
                    ...['draft', 'approved', 'issued']
                        .map((s) => DropdownMenuItem(value: s, child: Text(s))),
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
                  onPressed: _newDocument, icon: const Icon(Icons.add), label: const Text('New Document')),
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
                        child: Text('No documents.', style: TextStyle(color: Colors.grey.shade500)));
                  }
                  return Scrollbar(
                    child: SingleChildScrollView(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: DataTable(
                          headingRowColor: WidgetStatePropertyAll(Colors.grey.shade50),
                          columns: const [
                            DataColumn(label: Text('Document No.')),
                            DataColumn(label: Text('Type')),
                            DataColumn(label: Text('Status')),
                            DataColumn(label: Text('Teacher')),
                            DataColumn(label: Text('Room')),
                            DataColumn(label: Text('Date')),
                            DataColumn(label: Text('Items'), numeric: true),
                            DataColumn(label: Text('')),
                          ],
                          rows: [
                            for (final r in rows)
                              DataRow(cells: [
                                DataCell(Text('${r['document_no']}')),
                                DataCell(Text('${r['type']}')),
                                DataCell(_statusChip('${r['status']}')),
                                DataCell(Text('${r['teacher'] ?? '—'}')),
                                DataCell(Text('${r['room'] ?? '—'}')),
                                DataCell(Text('${r['document_date']}')),
                                DataCell(Text('${r['items_count'] ?? 0}')),
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

  Widget _statusChip(String s) {
    final color = switch (s) {
      'issued' => Colors.green,
      'approved' => Colors.blue,
      'draft' => Colors.grey,
      _ => Colors.grey,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
      child: Text(s, style: TextStyle(fontSize: 12, color: color)),
    );
  }
}

class _ItemRow {
  Object? supplyId;
  final TextEditingController qty = TextEditingController(text: '1');
}

/// New document form: header + supply line items.
class _NewDocumentDialog extends StatefulWidget {
  final ApiClient api;
  const _NewDocumentDialog({required this.api});

  @override
  State<_NewDocumentDialog> createState() => _NewDocumentDialogState();
}

class _NewDocumentDialogState extends State<_NewDocumentDialog> {
  late final Future<void> _ready;
  List<Map<String, dynamic>> _supplies = [];
  List<Map<String, dynamic>> _teachers = [];
  List<Map<String, dynamic>> _rooms = [];

  String _type = 'PAR';
  DateTime _date = DateTime.now();
  Object? _teacherId;
  Object? _roomId;
  final _entity = TextEditingController(text: 'Isabela State University - Cauayan Campus');
  final _requestedBy = TextEditingController();
  final _receivedBy = TextEditingController();
  final List<_ItemRow> _items = [_ItemRow()];
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _ready = _load();
  }

  Future<void> _load() async {
    final s = await widget.api.get('/admin/supplies');
    final t = await widget.api.get('/admin/teachers');
    final r = await widget.api.get('/admin/rooms');
    _supplies = ((s as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
    _teachers = ((t as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
    _rooms = ((r as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  @override
  void dispose() {
    _entity.dispose();
    _requestedBy.dispose();
    _receivedBy.dispose();
    for (final i in _items) {
      i.qty.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    final items = _items
        .where((i) => i.supplyId != null)
        .map((i) => {'supply_id': i.supplyId, 'quantity': int.tryParse(i.qty.text.trim()) ?? 1})
        .toList();
    if (items.isEmpty) {
      setState(() => _error = 'Add at least one item.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.post('/admin/documents', {
        'type': _type,
        'document_date':
            '${_date.year.toString().padLeft(4, '0')}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
        'teacher_id': _teacherId,
        'room_id': _roomId,
        'entity_name': _entity.text.trim(),
        'requested_by': _requestedBy.text.trim().isEmpty ? null : _requestedBy.text.trim(),
        'received_by': _receivedBy.text.trim().isEmpty ? null : _receivedBy.text.trim(),
        'items': items,
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Unexpected error: $e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('New Document'),
      content: SizedBox(
        width: 560,
        child: FutureBuilder<void>(
          future: _ready,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const SizedBox(height: 180, child: Center(child: CircularProgressIndicator()));
            }
            return SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: _type,
                        decoration: const InputDecoration(labelText: 'Type'),
                        items: const [
                          DropdownMenuItem(value: 'PAR', child: Text('PAR')),
                          DropdownMenuItem(value: 'ICS', child: Text('ICS')),
                          DropdownMenuItem(value: 'RIS', child: Text('RIS')),
                        ],
                        onChanged: (v) => setState(() => _type = v ?? 'PAR'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: InputDecorator(
                        decoration: const InputDecoration(labelText: 'Document date'),
                        child: Row(children: [
                          Expanded(child: Text(_date.toString().substring(0, 10))),
                          IconButton(
                            icon: const Icon(Icons.calendar_today, size: 18),
                            onPressed: () async {
                              final picked = await showDatePicker(
                                  context: context,
                                  initialDate: _date,
                                  firstDate: DateTime(2000),
                                  lastDate: DateTime(_date.year + 5));
                              if (picked != null) setState(() => _date = picked);
                            },
                          ),
                        ]),
                      ),
                    ),
                  ]),
                  const SizedBox(height: 12),
                  _dropdown('Teacher / custodian', _teachers, _teacherId, (v) => setState(() => _teacherId = v),
                      labelKey: 'full_name'),
                  const SizedBox(height: 12),
                  _dropdown('Room', _rooms, _roomId, (v) => setState(() => _roomId = v), labelKey: 'name'),
                  const SizedBox(height: 12),
                  TextField(controller: _entity, decoration: const InputDecoration(labelText: 'Entity name')),
                  const SizedBox(height: 12),
                  Row(children: [
                    Expanded(child: TextField(controller: _requestedBy, decoration: const InputDecoration(labelText: 'Requested by'))),
                    const SizedBox(width: 12),
                    Expanded(child: TextField(controller: _receivedBy, decoration: const InputDecoration(labelText: 'Received by'))),
                  ]),
                  const SizedBox(height: 16),
                  Row(children: [
                    const Text('Items', style: TextStyle(fontWeight: FontWeight.w600)),
                    const Spacer(),
                    TextButton.icon(
                      onPressed: () => setState(() => _items.add(_ItemRow())),
                      icon: const Icon(Icons.add, size: 18),
                      label: const Text('Add item'),
                    ),
                  ]),
                  for (int i = 0; i < _items.length; i++) _itemRow(i),
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

  Widget _itemRow(int i) {
    final row = _items[i];
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(children: [
        Expanded(
          flex: 3,
          child: DropdownButtonFormField<Object?>(
            initialValue: row.supplyId,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Supply', isDense: true),
            items: _supplies
                .map((s) => DropdownMenuItem(value: s['id'], child: Text('${s['name']} (${s['item_code']})')))
                .toList(),
            onChanged: (v) => setState(() => row.supplyId = v),
          ),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 80,
          child: TextField(
            controller: row.qty,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(labelText: 'Qty', isDense: true),
          ),
        ),
        IconButton(
          icon: Icon(Icons.remove_circle_outline, color: Colors.red.shade300),
          onPressed: _items.length == 1 ? null : () => setState(() => _items.removeAt(i)),
        ),
      ]),
    );
  }

  Widget _dropdown(String label, List<Map<String, dynamic>> items, Object? value,
      ValueChanged<Object?> onChanged,
      {required String labelKey}) {
    return DropdownButtonFormField<Object?>(
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        const DropdownMenuItem(value: null, child: Text('— None —')),
        ...items.map((i) => DropdownMenuItem(value: i['id'], child: Text('${i[labelKey] ?? i['name']}'))),
      ],
      onChanged: onChanged,
    );
  }
}

/// Document detail with approve/issue actions + open PDF.
class _DocumentDetailDialog extends StatefulWidget {
  final ApiClient api;
  final int id;
  const _DocumentDetailDialog({required this.api, required this.id});

  @override
  State<_DocumentDetailDialog> createState() => _DocumentDetailDialogState();
}

class _DocumentDetailDialogState extends State<_DocumentDetailDialog> {
  late Future<dynamic> _future;
  bool _busy = false;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    _future = widget.api.get('/admin/documents/${widget.id}');
  }

  void _reload() => setState(() => _future = widget.api.get('/admin/documents/${widget.id}'));

  void _snack(String m, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(m), backgroundColor: error ? Colors.red.shade700 : null));
  }

  Future<void> _action(String verb) async {
    setState(() => _busy = true);
    try {
      await widget.api.post('/admin/documents/${widget.id}/$verb');
      _changed = true;
      _reload();
      _snack('Document ${verb}d.');
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openPdf(String docNo) async {
    setState(() => _busy = true);
    try {
      final bytes = await widget.api.getBytes('/admin/documents/${widget.id}/pdf');
      final file = File('${Directory.systemTemp.path}${Platform.pathSeparator}$docNo.pdf');
      await file.writeAsBytes(bytes);
      await Process.run('cmd', ['/c', 'start', '', file.path]);
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Document'),
      content: SizedBox(
        width: 580,
        child: FutureBuilder<dynamic>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const SizedBox(height: 180, child: Center(child: CircularProgressIndicator()));
            }
            if (snap.hasError) return Text(snap.error.toString());
            final d = (snap.data as Map).cast<String, dynamic>();
            final items = ((d['items'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(children: [
                    Text('${d['document_no']}',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                    const SizedBox(width: 8),
                    Chip(label: Text('${d['type']} · ${d['status']}')),
                  ]),
                  const SizedBox(height: 8),
                  _kv('Date', d['document_date']),
                  _kv('Teacher', d['teacher']),
                  _kv('Room', d['room']),
                  _kv('Entity', d['entity_name']),
                  _kv('Prepared by', d['prepared_by']),
                  if (d['approved_by'] != null) _kv('Approved by', '${d['approved_by']} (${d['approved_at']})'),
                  if (d['issued_by'] != null) _kv('Issued by', '${d['issued_by']} (${d['issued_at']})'),
                  const Divider(),
                  const Text('Items', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 6),
                  Table(
                    columnWidths: const {0: FlexColumnWidth(3), 1: FlexColumnWidth(1), 2: FlexColumnWidth(1), 3: FlexColumnWidth(1.4)},
                    children: [
                      TableRow(
                        decoration: BoxDecoration(color: Colors.grey.shade100),
                        children: const [
                          Padding(padding: EdgeInsets.all(6), child: Text('Description', style: TextStyle(fontWeight: FontWeight.w600))),
                          Padding(padding: EdgeInsets.all(6), child: Text('Unit')),
                          Padding(padding: EdgeInsets.all(6), child: Text('Qty')),
                          Padding(padding: EdgeInsets.all(6), child: Text('Amount')),
                        ],
                      ),
                      for (final it in items)
                        TableRow(children: [
                          Padding(padding: const EdgeInsets.all(6), child: Text('${it['description'] ?? ''}')),
                          Padding(padding: const EdgeInsets.all(6), child: Text('${it['unit'] ?? ''}')),
                          Padding(padding: const EdgeInsets.all(6), child: Text('${it['quantity'] ?? ''}')),
                          Padding(padding: const EdgeInsets.all(6), child: Text('₱${it['amount'] ?? 0}')),
                        ]),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Wrap(spacing: 8, children: [
                    OutlinedButton.icon(
                      onPressed: _busy ? null : () => _openPdf('${d['type']}-${d['document_no']}'),
                      icon: const Icon(Icons.picture_as_pdf, size: 18),
                      label: const Text('Open PDF'),
                    ),
                    if (d['status'] == 'draft')
                      FilledButton.icon(
                        onPressed: _busy ? null : () => _action('approve'),
                        icon: const Icon(Icons.check, size: 18),
                        label: const Text('Approve'),
                      ),
                    if (d['status'] == 'approved')
                      FilledButton.icon(
                        style: FilledButton.styleFrom(backgroundColor: AppTheme.brand),
                        onPressed: _busy ? null : () => _action('issue'),
                        icon: const Icon(Icons.outbox, size: 18),
                        label: const Text('Issue'),
                      ),
                  ]),
                ],
              ),
            );
          },
        ),
      ),
      actions: [TextButton(onPressed: () => Navigator.pop(context, _changed), child: const Text('Close'))],
    );
  }

  Widget _kv(String k, Object? v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(width: 110, child: Text(k, style: const TextStyle(color: Colors.grey))),
          Expanded(child: Text('${v ?? '—'}')),
        ]),
      );
}
