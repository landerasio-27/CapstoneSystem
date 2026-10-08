import 'package:flutter/material.dart';
import 'resource_config.dart';

String _s(Object? v, [String fallback = '—']) {
  final t = v?.toString().trim() ?? '';
  return t.isEmpty ? fallback : t;
}

String _room(Map<String, dynamic> r) => '${r['code']} — ${r['name']}';

/// All admin resources, defined declaratively. Each maps to `/admin/<name>`
/// and mirrors the fields + validation of the matching web admin screen.
class Resources {
  static final rooms = ResourceConfig(
    path: '/admin/rooms',
    singular: 'Room',
    icon: Icons.meeting_room_outlined,
    titleOf: (r) => '${r['name']} (${r['code']})',
    columns: [
      ColumnSpec('Code', (r) => _s(r['code'])),
      ColumnSpec('Name', (r) => _s(r['name'])),
      ColumnSpec('Building', (r) => _s(r['building'])),
      ColumnSpec('Floor', (r) => _s(r['floor'])),
      ColumnSpec('Type', (r) => _s(r['type'])),
      ColumnSpec('Contents',
          (r) => '${r['equipment_count'] ?? 0} equip · ${r['supplies_count'] ?? 0} supply'),
    ],
    fields: const [
      FieldSpec(key: 'code', label: 'Code', required: true),
      FieldSpec(key: 'name', label: 'Name', required: true),
      FieldSpec(key: 'building', label: 'Building'),
      FieldSpec(key: 'floor', label: 'Floor'),
      FieldSpec(key: 'type', label: 'Type', hint: 'e.g. Laboratory, Office'),
      FieldSpec(key: 'is_active', label: 'Active', type: FieldType.boolean, defaultValue: true),
    ],
  );

  static final teachers = ResourceConfig(
    path: '/admin/teachers',
    singular: 'Teacher',
    icon: Icons.person_outline,
    titleOf: (r) => _s(r['full_name'], '${r['first_name']} ${r['last_name']}'),
    columns: [
      ColumnSpec('Employee ID', (r) => _s(r['employee_id'])),
      ColumnSpec('Name', (r) => _s(r['full_name'])),
      ColumnSpec('Department', (r) => _s(r['department'])),
      ColumnSpec('Position', (r) => _s(r['position'])),
      ColumnSpec('Room', (r) => _s(r['room_name'])),
      ColumnSpec('Equipment', (r) => '${r['equipment_count'] ?? 0}', numeric: true),
    ],
    fields: [
      const FieldSpec(key: 'employee_id', label: 'Employee ID', required: true),
      const FieldSpec(key: 'first_name', label: 'First name', required: true),
      const FieldSpec(key: 'last_name', label: 'Last name', required: true),
      const FieldSpec(key: 'department', label: 'Department'),
      const FieldSpec(key: 'position', label: 'Position'),
      const FieldSpec(key: 'email', label: 'Email'),
      const FieldSpec(key: 'contact_number', label: 'Contact number'),
      FieldSpec(
        key: 'room_id',
        label: 'Room',
        type: FieldType.select,
        optionsFrom: '/admin/rooms',
        optionLabel: _room,
        nullableSelect: true,
        intValue: true,
      ),
      const FieldSpec(key: 'is_active', label: 'Active', type: FieldType.boolean, defaultValue: true),
    ],
  );

  static final equipment = ResourceConfig(
    path: '/admin/equipment',
    singular: 'Equipment',
    icon: Icons.inventory_2_outlined,
    titleOf: (r) => '${r['name']} (${r['item_code']})',
    columns: [
      ColumnSpec('Item Code', (r) => _s(r['item_code'])),
      ColumnSpec('Name', (r) => _s(r['name'])),
      ColumnSpec('Category', (r) => _s(r['category'])),
      ColumnSpec('Qty', (r) => '${r['quantity_on_hand'] ?? 0}', numeric: true),
      ColumnSpec('Status', (r) => _s(r['status'])),
      ColumnSpec('Room', (r) => _s(r['room_name'])),
      ColumnSpec('Custodian', (r) => _s(r['teacher_name'])),
    ],
    fields: [
      const FieldSpec(key: 'item_code', label: 'Item code', required: true),
      const FieldSpec(key: 'property_number', label: 'Property number'),
      const FieldSpec(key: 'name', label: 'Name', required: true),
      const FieldSpec(key: 'description', label: 'Description', type: FieldType.multiline),
      const FieldSpec(key: 'category', label: 'Category'),
      const FieldSpec(key: 'unit', label: 'Unit', required: true, defaultValue: 'unit'),
      const FieldSpec(
          key: 'quantity_on_hand', label: 'Quantity on hand', type: FieldType.number, required: true, defaultValue: 1),
      const FieldSpec(key: 'unit_cost', label: 'Unit cost', type: FieldType.decimal, required: true, defaultValue: 0),
      const FieldSpec(
          key: 'reorder_level', label: 'Reorder level', type: FieldType.number, required: true, defaultValue: 0),
      FieldSpec(
        key: 'room_id',
        label: 'Room',
        type: FieldType.select,
        optionsFrom: '/admin/rooms',
        optionLabel: _room,
        nullableSelect: true,
        intValue: true,
      ),
      FieldSpec(
        key: 'teacher_id',
        label: 'Custodian (teacher)',
        type: FieldType.select,
        optionsFrom: '/admin/teachers',
        optionLabel: (r) => _s(r['full_name']),
        nullableSelect: true,
        intValue: true,
      ),
      const FieldSpec(
        key: 'status',
        label: 'Status',
        type: FieldType.select,
        required: true,
        defaultValue: 'available',
        options: [
          SelectOption('available', 'Available'),
          SelectOption('issued', 'Issued'),
          SelectOption('damaged', 'Damaged'),
          SelectOption('missing', 'Missing'),
          SelectOption('misplaced', 'Misplaced'),
        ],
      ),
      const FieldSpec(key: 'date_acquired', label: 'Date acquired', type: FieldType.date),
    ],
  );

  static final supplies = ResourceConfig(
    path: '/admin/supplies',
    singular: 'Supply',
    icon: Icons.inventory_outlined,
    titleOf: (r) => '${r['name']} (${r['item_code']})',
    columns: [
      ColumnSpec('Item Code', (r) => _s(r['item_code'])),
      ColumnSpec('Name', (r) => _s(r['name'])),
      ColumnSpec('Category', (r) => _s(r['category'])),
      ColumnSpec('Qty', (r) => '${r['quantity_on_hand'] ?? 0}', numeric: true),
      ColumnSpec('Unit', (r) => _s(r['unit'])),
      ColumnSpec('Unit Cost', (r) => '₱${(r['unit_cost'] ?? 0)}', numeric: true),
      ColumnSpec('Room', (r) => _s(r['room_name'])),
    ],
    fields: [
      const FieldSpec(key: 'item_code', label: 'Item code', required: true),
      const FieldSpec(key: 'name', label: 'Name', required: true),
      const FieldSpec(key: 'description', label: 'Description', type: FieldType.multiline),
      const FieldSpec(key: 'category', label: 'Category'),
      const FieldSpec(key: 'unit', label: 'Unit', required: true, defaultValue: 'pcs'),
      const FieldSpec(
          key: 'quantity_on_hand', label: 'Quantity on hand', type: FieldType.number, required: true, defaultValue: 0),
      const FieldSpec(key: 'unit_cost', label: 'Unit cost', type: FieldType.decimal, required: true, defaultValue: 0),
      const FieldSpec(
          key: 'reorder_level', label: 'Reorder level', type: FieldType.number, required: true, defaultValue: 0),
      FieldSpec(
        key: 'room_id',
        label: 'Room',
        type: FieldType.select,
        optionsFrom: '/admin/rooms',
        optionLabel: _room,
        nullableSelect: true,
        intValue: true,
      ),
      const FieldSpec(key: 'is_active', label: 'Active', type: FieldType.boolean, defaultValue: true),
    ],
  );

  static final users = ResourceConfig(
    path: '/admin/users',
    singular: 'User',
    icon: Icons.people_alt_outlined,
    titleOf: (r) => _s(r['name']),
    columns: [
      ColumnSpec('Name', (r) => _s(r['name'])),
      ColumnSpec('Email', (r) => _s(r['email'])),
      ColumnSpec('Employee ID', (r) => _s(r['employee_id'])),
      ColumnSpec('Role', (r) => _s(r['role_display'])),
      ColumnSpec('Active', (r) => (r['is_active'] == true) ? 'Yes' : 'No'),
    ],
    fields: [
      const FieldSpec(key: 'name', label: 'Full name', required: true),
      const FieldSpec(key: 'email', label: 'Email', required: true),
      const FieldSpec(key: 'employee_id', label: 'Employee ID'),
      const FieldSpec(key: 'contact_number', label: 'Contact number'),
      FieldSpec(
        key: 'role_id',
        label: 'Role',
        type: FieldType.select,
        required: true,
        optionsFrom: '/admin/roles',
        optionLabel: (r) => _s(r['display_name']),
        intValue: true,
      ),
      const FieldSpec(
        key: 'password',
        label: 'Password',
        obscure: true,
        requiredOnCreate: true,
        hint: 'Min 8 characters. Leave blank when editing to keep current.',
      ),
    ],
  );

  static final announcements = ResourceConfig(
    path: '/admin/announcements',
    singular: 'Announcement',
    icon: Icons.campaign_outlined,
    titleOf: (r) => _s(r['title']),
    columns: [
      ColumnSpec('Title', (r) => _s(r['title'])),
      ColumnSpec('Priority', (r) => _s(r['priority'])),
      ColumnSpec('Audience', (r) => _s(r['audience'])),
      ColumnSpec('Published', (r) => _s(r['published_at'])),
      ColumnSpec('Expires', (r) => _s(r['expires_at'])),
      ColumnSpec('By', (r) => _s(r['created_by_name'])),
    ],
    fields: const [
      FieldSpec(key: 'title', label: 'Title', required: true),
      FieldSpec(key: 'body', label: 'Message', type: FieldType.multiline, required: true),
      FieldSpec(
        key: 'priority',
        label: 'Priority',
        type: FieldType.select,
        required: true,
        defaultValue: 'normal',
        options: [
          SelectOption('normal', 'Normal'),
          SelectOption('important', 'Important'),
          SelectOption('urgent', 'Urgent'),
        ],
      ),
      FieldSpec(
        key: 'audience',
        label: 'Audience',
        type: FieldType.select,
        required: true,
        defaultValue: 'all',
        options: [
          SelectOption('all', 'Everyone'),
          SelectOption('admin', 'Administrators'),
          SelectOption('inspection_staff', 'Inspection staff'),
        ],
      ),
      FieldSpec(key: 'expires_at', label: 'Expires on', type: FieldType.date),
    ],
  );

  static final activityLogs = ResourceConfig(
    path: '/admin/activity-logs',
    singular: 'Activity Log',
    icon: Icons.history,
    canCreate: false,
    canEdit: false,
    canDelete: false,
    titleOf: (r) => _s(r['description']),
    columns: [
      ColumnSpec('When', (r) => _s(r['created_at'])),
      ColumnSpec('User', (r) => _s(r['user'])),
      ColumnSpec('Module', (r) => _s(r['module'])),
      ColumnSpec('Action', (r) => _s(r['action'])),
      ColumnSpec('Description', (r) => _s(r['description'])),
    ],
  );
}
