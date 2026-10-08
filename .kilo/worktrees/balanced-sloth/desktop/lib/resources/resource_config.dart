import 'package:flutter/material.dart';

/// The kind of input a form field renders.
enum FieldType { text, multiline, number, decimal, boolean, date, select }

/// One choice in a select field.
class SelectOption {
  final Object? value;
  final String label;
  const SelectOption(this.value, this.label);
}

/// Describes a single editable field on a resource form.
class FieldSpec {
  final String key;
  final String label;
  final FieldType type;
  final bool required;

  /// Required only when creating (e.g. a user password — optional on edit).
  final bool requiredOnCreate;

  /// Static choices for a [FieldType.select].
  final List<SelectOption> options;

  /// Load select choices from this admin API path (e.g. '/admin/rooms').
  final String? optionsFrom;
  final String optionValueKey;
  final String Function(Map<String, dynamic> row)? optionLabel;

  /// Whether a nullable select shows a "— None —" entry.
  final bool nullableSelect;

  /// Select value should be sent as an int (foreign keys) rather than a string.
  final bool intValue;

  final bool obscure;
  final Object? defaultValue;
  final String? hint;

  const FieldSpec({
    required this.key,
    required this.label,
    this.type = FieldType.text,
    this.required = false,
    this.requiredOnCreate = false,
    this.options = const [],
    this.optionsFrom,
    this.optionValueKey = 'id',
    this.optionLabel,
    this.nullableSelect = false,
    this.intValue = false,
    this.obscure = false,
    this.defaultValue,
    this.hint,
  });
}

/// A column in the resource list table.
class ColumnSpec {
  final String label;
  final String Function(Map<String, dynamic> row) value;
  final bool numeric;
  final Widget Function(Map<String, dynamic> row)? cell;
  const ColumnSpec(this.label, this.value, {this.numeric = false, this.cell});
}

/// Everything the generic list + form screens need to manage one resource.
class ResourceConfig {
  final String path; // e.g. '/admin/rooms'
  final String singular; // e.g. 'Room'
  final IconData icon;
  final List<ColumnSpec> columns;
  final List<FieldSpec> fields;
  final bool canCreate;
  final bool canEdit;
  final bool canDelete;
  final bool searchable;
  final String Function(Map<String, dynamic> row) titleOf;

  const ResourceConfig({
    required this.path,
    required this.singular,
    required this.icon,
    required this.columns,
    required this.titleOf,
    this.fields = const [],
    this.canCreate = true,
    this.canEdit = true,
    this.canDelete = true,
    this.searchable = true,
  });
}
