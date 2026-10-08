import 'package:flutter/material.dart';
import '../services/api_client.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  static const _reports = <(String, String)>[
    ('Inventory Summary', '/admin/reports/inventory-summary'),
    ('Stock Movement', '/admin/reports/stock-movement'),
    ('Supply Issuance', '/admin/reports/supply-issuance'),
    ('Inspection Results', '/admin/reports/inspection-results'),
    ('Missing & Misplaced Equipment', '/admin/reports/missing-equipment'),
    ('Document Register', '/admin/reports/document-register'),
  ];

  final _api = ApiClient();
  String _report = _reports.first.$2;
  List<Map<String, dynamic>> _rooms = [];
  List<String> _categories = [];
  Object? _roomId;
  String? _category;
  String? _from;
  String? _to;

  Map<String, dynamic>? _result;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    try {
      final data = await _api.get('/admin/reports/options') as Map<String, dynamic>;
      setState(() {
        _rooms = ((data['rooms'] as List?) ?? []).map((e) => (e as Map).cast<String, dynamic>()).toList();
        _categories = ((data['categories'] as List?) ?? []).map((e) => e.toString()).toList();
      });
    } catch (_) {/* filters just stay empty */}
  }

  Future<void> _generate() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final params = <String, String>{
        if (_roomId != null) 'room_id': '$_roomId',
        if (_category != null) 'category': _category!,
        if (_from != null) 'date_from': _from!,
        if (_to != null) 'date_to': _to!,
      };
      final qs = params.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&');
      final data = await _api.get(qs.isEmpty ? _report : '$_report?$qs') as Map<String, dynamic>;
      setState(() => _result = data);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickDate(bool from) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
        context: context, initialDate: now, firstDate: DateTime(2000), lastDate: DateTime(now.year + 1));
    if (picked != null) {
      final s = '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
      setState(() => from ? _from = s : _to = s);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Wrap(
                spacing: 12,
                runSpacing: 12,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  SizedBox(
                    width: 260,
                    child: DropdownButtonFormField<String>(
                      initialValue: _report,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Report', isDense: true),
                      items: _reports
                          .map((r) => DropdownMenuItem(value: r.$2, child: Text(r.$1)))
                          .toList(),
                      onChanged: (v) => setState(() => _report = v ?? _report),
                    ),
                  ),
                  SizedBox(
                    width: 200,
                    child: DropdownButtonFormField<Object?>(
                      initialValue: _roomId,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Room', isDense: true),
                      items: [
                        const DropdownMenuItem(value: null, child: Text('All rooms')),
                        ..._rooms.map((r) => DropdownMenuItem(value: r['id'], child: Text('${r['label']}'))),
                      ],
                      onChanged: (v) => setState(() => _roomId = v),
                    ),
                  ),
                  SizedBox(
                    width: 180,
                    child: DropdownButtonFormField<String?>(
                      initialValue: _category,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Category', isDense: true),
                      items: [
                        const DropdownMenuItem(value: null, child: Text('All')),
                        ..._categories.map((c) => DropdownMenuItem(value: c, child: Text(c))),
                      ],
                      onChanged: (v) => setState(() => _category = v),
                    ),
                  ),
                  _dateChip('From', _from, () => _pickDate(true), () => setState(() => _from = null)),
                  _dateChip('To', _to, () => _pickDate(false), () => setState(() => _to = null)),
                  FilledButton.icon(
                    onPressed: _loading ? null : _generate,
                    icon: const Icon(Icons.play_arrow),
                    label: const Text('Generate'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: Card(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                      ? Center(child: Text(_error!, style: TextStyle(color: Colors.red.shade700)))
                      : _result == null
                          ? Center(
                              child: Text('Choose a report and press Generate.',
                                  style: TextStyle(color: Colors.grey.shade500)))
                          : _resultTable(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _dateChip(String label, String? value, VoidCallback onPick, VoidCallback onClear) {
    return InputChip(
      label: Text(value == null ? label : '$label: $value'),
      avatar: const Icon(Icons.calendar_today, size: 16),
      onPressed: onPick,
      onDeleted: value == null ? null : onClear,
    );
  }

  Widget _resultTable() {
    final headings = ((_result!['headings'] as List?) ?? []).map((e) => e.toString()).toList();
    final rows = ((_result!['rows'] as List?) ?? []).map((r) => (r as List)).toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Text('${_result!['title']}  ·  ${rows.length} row(s)',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
        ),
        const Divider(height: 1),
        Expanded(
          child: rows.isEmpty
              ? Center(child: Text('No data for this report.', style: TextStyle(color: Colors.grey.shade500)))
              : Scrollbar(
                  child: SingleChildScrollView(
                    child: SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: DataTable(
                        headingRowColor: WidgetStatePropertyAll(Colors.grey.shade50),
                        columns: [for (final h in headings) DataColumn(label: Text(h))],
                        rows: [
                          for (final row in rows)
                            DataRow(cells: [for (final cell in row) DataCell(Text('${cell ?? ''}'))]),
                        ],
                      ),
                    ),
                  ),
                ),
        ),
      ],
    );
  }
}
