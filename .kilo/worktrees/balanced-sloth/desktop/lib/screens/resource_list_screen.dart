import 'package:flutter/material.dart';
import '../resources/resource_config.dart';
import '../resources/resource_service.dart';
import '../theme/app_theme.dart';
import '../widgets/resource_form_dialog.dart';

/// Generic list screen for any [ResourceConfig]: a search bar, an "Add" button,
/// a data table, and row edit/delete actions — all driven by the config.
class ResourceListScreen extends StatefulWidget {
  final ResourceConfig config;
  const ResourceListScreen({super.key, required this.config});

  @override
  State<ResourceListScreen> createState() => _ResourceListScreenState();
}

class _ResourceListScreenState extends State<ResourceListScreen> {
  final _service = ResourceService();
  final _searchCtrl = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future;
  String _search = '';

  ResourceConfig get config => widget.config;

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

  Future<List<Map<String, dynamic>>> _load() =>
      _service.list(config.path, search: _search);

  void _reload() => setState(() => _future = _load());

  Future<void> _openForm([Map<String, dynamic>? row]) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => ResourceFormDialog(config: config, existing: row),
    );
    if (saved == true) {
      _reload();
      _snack('${config.singular} ${row == null ? 'created' : 'updated'}.');
    }
  }

  Future<void> _delete(Map<String, dynamic> row) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Delete ${config.singular}?'),
        content: Text('Delete "${config.titleOf(row)}"? This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _service.remove(config.path, row['id'] as Object);
      _reload();
      _snack('${config.singular} deleted.');
    } catch (e) {
      _snack(e.toString(), error: true);
    }
  }

  void _snack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? Colors.red.shade700 : null,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final hasActions = config.canEdit || config.canDelete;
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              if (config.searchable)
                SizedBox(
                  width: 320,
                  child: TextField(
                    controller: _searchCtrl,
                    decoration: InputDecoration(
                      hintText: 'Search ${config.singular.toLowerCase()}…',
                      prefixIcon: const Icon(Icons.search),
                      isDense: true,
                      suffixIcon: _search.isEmpty
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.clear),
                              onPressed: () {
                                _searchCtrl.clear();
                                setState(() => _search = '');
                                _reload();
                              },
                            ),
                    ),
                    onSubmitted: (v) {
                      setState(() => _search = v);
                      _reload();
                    },
                  ),
                ),
              const Spacer(),
              IconButton(
                tooltip: 'Refresh',
                onPressed: _reload,
                icon: const Icon(Icons.refresh),
              ),
              const SizedBox(width: 8),
              if (config.canCreate)
                FilledButton.icon(
                  onPressed: () => _openForm(),
                  icon: const Icon(Icons.add),
                  label: Text('Add ${config.singular}'),
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
                  if (snap.hasError) {
                    return _error(snap.error.toString());
                  }
                  final rows = snap.data ?? const [];
                  if (rows.isEmpty) {
                    return Center(
                      child: Text('No ${config.singular.toLowerCase()} records.',
                          style: TextStyle(color: Colors.grey.shade500)),
                    );
                  }
                  return _table(rows, hasActions);
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _table(List<Map<String, dynamic>> rows, bool hasActions) {
    return Scrollbar(
      child: SingleChildScrollView(
        child: SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: ConstrainedBox(
            constraints: BoxConstraints(minWidth: MediaQuery.of(context).size.width - 340),
            child: DataTable(
              headingRowColor: WidgetStatePropertyAll(Colors.grey.shade50),
              columns: [
                for (final c in config.columns)
                  DataColumn(label: Text(c.label), numeric: c.numeric),
                if (hasActions) const DataColumn(label: Text('')),
              ],
              rows: [
                for (final row in rows)
                  DataRow(cells: [
                    for (final c in config.columns)
                      DataCell(c.cell?.call(row) ?? Text(c.value(row))),
                    if (hasActions)
                      DataCell(Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (config.canEdit)
                            IconButton(
                              tooltip: 'Edit',
                              icon: const Icon(Icons.edit_outlined, size: 18, color: AppTheme.brand),
                              onPressed: () => _openForm(row),
                            ),
                          if (config.canDelete)
                            IconButton(
                              tooltip: 'Delete',
                              icon: Icon(Icons.delete_outline, size: 18, color: Colors.red.shade400),
                              onPressed: () => _delete(row),
                            ),
                        ],
                      )),
                  ]),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _error(String message) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.cloud_off, size: 44, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey.shade700)),
            const SizedBox(height: 12),
            FilledButton.icon(onPressed: _reload, icon: const Icon(Icons.refresh), label: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}
