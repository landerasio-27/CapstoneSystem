import 'package:flutter/material.dart';
import '../services/api_client.dart';

class InspectionRecordsScreen extends StatefulWidget {
  const InspectionRecordsScreen({super.key});

  @override
  State<InspectionRecordsScreen> createState() => _InspectionRecordsScreenState();
}

class _InspectionRecordsScreenState extends State<InspectionRecordsScreen> {
  final _api = ApiClient();
  final _searchCtrl = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;
  String _search = '';

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
    final q = _search.isEmpty ? '' : '?search=${Uri.encodeQueryComponent(_search)}';
    final data = await _api.get('/admin/records$q');
    return ((data as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _openDetail(Map<String, dynamic> row) async {
    showDialog<void>(
      context: context,
      builder: (_) => _RecordDetailDialog(api: _api, id: row['id'] as int, reference: '${row['reference']}'),
    );
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
                width: 320,
                child: TextField(
                  controller: _searchCtrl,
                  decoration: const InputDecoration(
                      hintText: 'Search teacher, room, inspector, ref…',
                      prefixIcon: Icon(Icons.search),
                      isDense: true),
                  onSubmitted: (v) {
                    setState(() => _search = v);
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
                  if (snap.hasError) {
                    return Center(child: Text(snap.error.toString()));
                  }
                  final rows = snap.data ?? const [];
                  if (rows.isEmpty) {
                    return Center(
                        child: Text('No completed inspection records.',
                            style: TextStyle(color: Colors.grey.shade500)));
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
                            DataColumn(label: Text('Inspector')),
                            DataColumn(label: Text('Verified'), numeric: true),
                            DataColumn(label: Text('Missing'), numeric: true),
                            DataColumn(label: Text('Status')),
                            DataColumn(label: Text('Submitted')),
                            DataColumn(label: Text('')),
                          ],
                          rows: [
                            for (final r in rows)
                              DataRow(cells: [
                                DataCell(Text('${r['reference']}')),
                                DataCell(Text('${r['teacher'] ?? '—'}')),
                                DataCell(Text('${r['room'] ?? '—'}')),
                                DataCell(Text('${r['inspector'] ?? '—'}')),
                                DataCell(Text('${r['verified_count']}/${r['total_items']}')),
                                DataCell(Text('${r['missing_count']}')),
                                DataCell(_statusChip('${r['status_label']}', (r['missing_count'] ?? 0) > 0)),
                                DataCell(Text('${r['submitted_at'] ?? '—'}')),
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

  Widget _statusChip(String label, bool warn) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: (warn ? Colors.amber : Colors.green).withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label,
          style: TextStyle(fontSize: 12, color: warn ? Colors.amber.shade900 : Colors.green.shade800)),
    );
  }
}

class _RecordDetailDialog extends StatelessWidget {
  final ApiClient api;
  final int id;
  final String reference;
  const _RecordDetailDialog({required this.api, required this.id, required this.reference});

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('Inspection $reference'),
      content: SizedBox(
        width: 560,
        child: FutureBuilder<dynamic>(
          future: api.get('/admin/records/$id'),
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
                  _kv('Teacher', d['teacher']),
                  _kv('Room', d['room']),
                  _kv('Inspector', d['inspector']),
                  _kv('Status', d['status_label']),
                  _kv('Verified', '${d['verified_count']}/${d['total_items']}  ·  missing ${d['missing_count']}  ·  misplaced ${d['misplaced_count']}'),
                  _kv('Submitted', d['submitted_at']),
                  if ((d['notes'] ?? '').toString().isNotEmpty) _kv('Notes', d['notes']),
                  const Divider(),
                  const Text('Items', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 6),
                  for (final it in items)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text('${it['equipment'] ?? '—'} (${it['item_code'] ?? '—'})'),
                      subtitle: (it['remarks'] ?? '').toString().isEmpty ? null : Text('${it['remarks']}'),
                      trailing: Text('${it['status'] ?? ''}'),
                    ),
                ],
              ),
            );
          },
        ),
      ),
      actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close'))],
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
