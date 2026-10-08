import 'package:flutter/material.dart';
import '../resources/resource_config.dart';
import '../resources/resource_service.dart';
import '../services/api_client.dart';

/// A dialog that builds an add/edit form from a [ResourceConfig]'s field specs.
/// Returns `true` via Navigator when a record was created or updated.
class ResourceFormDialog extends StatefulWidget {
  final ResourceConfig config;
  final Map<String, dynamic>? existing; // null => create

  const ResourceFormDialog({super.key, required this.config, this.existing});

  @override
  State<ResourceFormDialog> createState() => _ResourceFormDialogState();
}

class _ResourceFormDialogState extends State<ResourceFormDialog> {
  final _formKey = GlobalKey<FormState>();
  final _service = ResourceService();
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, Object?> _values = {};
  final Map<String, List<SelectOption>> _dynamicOptions = {};
  late final Future<void> _ready;
  bool _saving = false;
  String? _error;

  bool get _isEdit => widget.existing != null;

  @override
  void initState() {
    super.initState();
    for (final f in widget.config.fields) {
      final initial = _isEdit ? widget.existing![f.key] : f.defaultValue;
      switch (f.type) {
        case FieldType.text:
        case FieldType.multiline:
        case FieldType.number:
        case FieldType.decimal:
          _controllers[f.key] = TextEditingController(text: initial?.toString() ?? '');
          break;
        case FieldType.boolean:
          _values[f.key] = initial == true;
          break;
        case FieldType.date:
        case FieldType.select:
          _values[f.key] = initial;
          break;
      }
    }
    _ready = _loadOptions();
  }

  Future<void> _loadOptions() async {
    for (final f in widget.config.fields) {
      if (f.optionsFrom == null) continue;
      final rows = await _service.list(f.optionsFrom!);
      _dynamicOptions[f.key] = rows
          .map((r) => SelectOption(r[f.optionValueKey], f.optionLabel?.call(r) ?? '${r[f.optionValueKey]}'))
          .toList();
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  bool _isRequired(FieldSpec f) => f.required || (!_isEdit && f.requiredOnCreate);

  Map<String, dynamic> _buildPayload() {
    final payload = <String, dynamic>{};
    for (final f in widget.config.fields) {
      switch (f.type) {
        case FieldType.text:
        case FieldType.multiline:
          final t = _controllers[f.key]!.text.trim();
          if (f.key == 'password' && t.isEmpty) break; // leave unchanged on edit
          payload[f.key] = t.isEmpty ? null : t;
          break;
        case FieldType.number:
          final t = _controllers[f.key]!.text.trim();
          payload[f.key] = t.isEmpty ? null : int.tryParse(t);
          break;
        case FieldType.decimal:
          final t = _controllers[f.key]!.text.trim();
          payload[f.key] = t.isEmpty ? null : double.tryParse(t);
          break;
        case FieldType.boolean:
          payload[f.key] = _values[f.key] == true;
          break;
        case FieldType.date:
        case FieldType.select:
          payload[f.key] = _values[f.key];
          break;
      }
    }
    return payload;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    // Required selects aren't covered by TextFormField validators.
    for (final f in widget.config.fields) {
      if (f.type == FieldType.select && _isRequired(f) && _values[f.key] == null) {
        setState(() => _error = 'Please choose a ${f.label.toLowerCase()}.');
        return;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final payload = _buildPayload();
      if (_isEdit) {
        await _service.update(widget.config.path, widget.existing!['id'] as Object, payload);
      } else {
        await _service.create(widget.config.path, payload);
      }
      if (mounted) Navigator.of(context).pop(true);
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
      title: Text('${_isEdit ? 'Edit' : 'Add'} ${widget.config.singular}'),
      content: SizedBox(
        width: 460,
        child: FutureBuilder<void>(
          future: _ready,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const SizedBox(
                  height: 120, child: Center(child: CircularProgressIndicator()));
            }
            return SingleChildScrollView(
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    for (final f in widget.config.fields) ...[
                      _buildField(f),
                      const SizedBox(height: 12),
                    ],
                    if (_error != null)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Colors.red.shade50,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.red.shade200),
                        ),
                        child: Text(_error!, style: TextStyle(color: Colors.red.shade700)),
                      ),
                  ],
                ),
              ),
            );
          },
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox(
                  height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : Text(_isEdit ? 'Save' : 'Create'),
        ),
      ],
    );
  }

  Widget _buildField(FieldSpec f) {
    switch (f.type) {
      case FieldType.boolean:
        return SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(f.label),
          value: _values[f.key] == true,
          onChanged: (v) => setState(() => _values[f.key] = v),
        );
      case FieldType.date:
        final current = _values[f.key] as String?;
        return InputDecorator(
          decoration: InputDecoration(labelText: f.label),
          child: Row(
            children: [
              Expanded(child: Text(current ?? 'Not set',
                  style: TextStyle(color: current == null ? Colors.grey : null))),
              if (current != null)
                IconButton(
                  icon: const Icon(Icons.clear, size: 18),
                  onPressed: () => setState(() => _values[f.key] = null),
                ),
              IconButton(
                icon: const Icon(Icons.calendar_today, size: 18),
                onPressed: () async {
                  final now = DateTime.now();
                  final picked = await showDatePicker(
                    context: context,
                    initialDate: current != null ? DateTime.tryParse(current) ?? now : now,
                    firstDate: DateTime(2000),
                    lastDate: DateTime(now.year + 10),
                  );
                  if (picked != null) {
                    setState(() => _values[f.key] =
                        '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}');
                  }
                },
              ),
            ],
          ),
        );
      case FieldType.select:
        final options = <SelectOption>[
          if (f.nullableSelect) const SelectOption(null, '— None —'),
          ...(f.optionsFrom != null ? (_dynamicOptions[f.key] ?? const []) : f.options),
        ];
        return DropdownButtonFormField<Object?>(
          initialValue: options.any((o) => o.value == _values[f.key]) ? _values[f.key] : null,
          isExpanded: true,
          decoration: InputDecoration(labelText: f.label + (_isRequired(f) ? ' *' : '')),
          items: options
              .map((o) => DropdownMenuItem<Object?>(value: o.value, child: Text(o.label)))
              .toList(),
          onChanged: (v) => setState(() => _values[f.key] = v),
        );
      default:
        final isNum = f.type == FieldType.number || f.type == FieldType.decimal;
        return TextFormField(
          controller: _controllers[f.key],
          obscureText: f.obscure,
          maxLines: f.type == FieldType.multiline ? 3 : 1,
          keyboardType: isNum ? const TextInputType.numberWithOptions(decimal: true) : null,
          decoration: InputDecoration(
            labelText: f.label + (_isRequired(f) ? ' *' : ''),
            hintText: f.hint,
          ),
          validator: (v) {
            final t = v?.trim() ?? '';
            if (_isRequired(f) && t.isEmpty) return '${f.label} is required';
            if (isNum && t.isNotEmpty && num.tryParse(t) == null) return 'Enter a valid number';
            return null;
          },
        );
    }
  }
}
