import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme/app_theme.dart';

class QrCodesScreen extends StatefulWidget {
  const QrCodesScreen({super.key});

  @override
  State<QrCodesScreen> createState() => _QrCodesScreenState();
}

class _QrCodesScreenState extends State<QrCodesScreen> {
  final _api = ApiClient();
  final _searchCtrl = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;
  String _type = 'teacher';
  String _search = '';
  String? _printed;

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
      'type': _type,
      if (_search.isNotEmpty) 'search': _search,
      if (_printed != null) 'printed': _printed!,
    };
    final qs = params.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&');
    final data = await _api.get('/admin/qrcodes?$qs');
    return ((data as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _openQr(Map<String, dynamic> row) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _QrDialog(api: _api, row: row),
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
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'teacher', label: Text('Teachers'), icon: Icon(Icons.person_outline)),
                  ButtonSegment(value: 'equipment', label: Text('Equipment'), icon: Icon(Icons.inventory_2_outlined)),
                ],
                selected: {_type},
                onSelectionChanged: (s) {
                  setState(() => _type = s.first);
                  _reload();
                },
              ),
              const SizedBox(width: 16),
              SizedBox(
                width: 260,
                child: TextField(
                  controller: _searchCtrl,
                  decoration: const InputDecoration(
                      hintText: 'Search…', prefixIcon: Icon(Icons.search), isDense: true),
                  onSubmitted: (v) {
                    setState(() => _search = v);
                    _reload();
                  },
                ),
              ),
              const SizedBox(width: 12),
              SizedBox(
                width: 150,
                child: DropdownButtonFormField<String?>(
                  initialValue: _printed,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Printed', isDense: true),
                  items: const [
                    DropdownMenuItem(value: null, child: Text('All')),
                    DropdownMenuItem(value: 'printed', child: Text('Printed')),
                    DropdownMenuItem(value: 'unprinted', child: Text('Unprinted')),
                  ],
                  onChanged: (v) {
                    setState(() => _printed = v);
                    _reload();
                  },
                ),
              ),
              const Spacer(),
              IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
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
                        child: Text('No records.', style: TextStyle(color: Colors.grey.shade500)));
                  }
                  return Scrollbar(
                    child: SingleChildScrollView(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: DataTable(
                          headingRowColor: WidgetStatePropertyAll(Colors.grey.shade50),
                          columns: const [
                            DataColumn(label: Text('Name')),
                            DataColumn(label: Text('Ref')),
                            DataColumn(label: Text('QR Code')),
                            DataColumn(label: Text('Printed')),
                            DataColumn(label: Text('')),
                          ],
                          rows: [
                            for (final r in rows)
                              DataRow(cells: [
                                DataCell(Text('${r['label']}')),
                                DataCell(Text('${r['sub_label'] ?? '—'}')),
                                DataCell(Text('${r['code']}', style: const TextStyle(fontFamily: 'monospace'))),
                                DataCell(r['printed_at'] == null
                                    ? Text('Not printed', style: TextStyle(color: Colors.grey.shade500))
                                    : Text('${r['printed_at']}', style: TextStyle(color: Colors.green.shade700))),
                                DataCell(TextButton.icon(
                                  icon: const Icon(Icons.qr_code_2, size: 18),
                                  label: const Text('View / Print'),
                                  onPressed: () => _openQr(r),
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
}

class _QrDialog extends StatefulWidget {
  final ApiClient api;
  final Map<String, dynamic> row;
  const _QrDialog({required this.api, required this.row});

  @override
  State<_QrDialog> createState() => _QrDialogState();
}

class _QrDialogState extends State<_QrDialog> {
  late Future<Uint8List> _png;
  bool _busy = false;
  bool _changed = false;

  @override
  void initState() {
    super.initState();
    _png = _loadPng();
  }

  Future<Uint8List> _loadPng() async {
    final bytes = await widget.api.getBytes('/admin/qrcodes/${widget.row['type']}/${widget.row['id']}/png');
    return Uint8List.fromList(bytes);
  }

  void _snack(String m, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(m), backgroundColor: error ? Colors.red.shade700 : null));
  }

  Future<void> _savePng() async {
    setState(() => _busy = true);
    try {
      final bytes = await _png;
      final file = File('${Directory.systemTemp.path}${Platform.pathSeparator}${widget.row['code']}.png');
      await file.writeAsBytes(bytes);
      await Process.run('cmd', ['/c', 'start', '', file.path]);
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _markPrinted() async {
    setState(() => _busy = true);
    try {
      await widget.api.post('/admin/qrcodes/${widget.row['qr_id']}/mark-printed');
      _changed = true;
      _snack('Marked as printed.');
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() => _busy = false);
      _snack(e.toString(), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('${widget.row['label']}'),
      content: SizedBox(
        width: 360,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('${widget.row['sub_label'] ?? ''}', style: TextStyle(color: Colors.grey.shade600)),
            const SizedBox(height: 12),
            FutureBuilder<Uint8List>(
              future: _png,
              builder: (context, snap) {
                if (snap.connectionState == ConnectionState.waiting) {
                  return const SizedBox(height: 260, child: Center(child: CircularProgressIndicator()));
                }
                if (snap.hasError) {
                  return SizedBox(height: 120, child: Center(child: Text(snap.error.toString())));
                }
                return Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    border: Border.all(color: Colors.grey.shade200),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Image.memory(snap.data!, width: 280, fit: BoxFit.contain),
                );
              },
            ),
            const SizedBox(height: 8),
            SelectableText('${widget.row['code']}',
                style: const TextStyle(fontFamily: 'monospace', fontSize: 12)),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, _changed), child: const Text('Close')),
        OutlinedButton.icon(
          onPressed: _busy ? null : _savePng,
          icon: const Icon(Icons.download, size: 18),
          label: const Text('Save PNG'),
        ),
        FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: AppTheme.brand),
          onPressed: _busy ? null : _markPrinted,
          icon: const Icon(Icons.print, size: 18),
          label: const Text('Mark printed'),
        ),
      ],
    );
  }
}
