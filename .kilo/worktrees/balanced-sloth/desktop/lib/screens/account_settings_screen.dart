import 'package:flutter/material.dart';
import '../services/api_client.dart';

/// Account settings — edit own profile + change password (mirrors the web).
class AccountSettingsScreen extends StatefulWidget {
  const AccountSettingsScreen({super.key});

  @override
  State<AccountSettingsScreen> createState() => _AccountSettingsScreenState();
}

class _AccountSettingsScreenState extends State<AccountSettingsScreen> {
  final _api = ApiClient();
  late Future<Map<String, dynamic>> _future;

  final _name = TextEditingController();
  final _email = TextEditingController();
  final _employeeId = TextEditingController();
  final _contact = TextEditingController();
  final _current = TextEditingController();
  final _newPass = TextEditingController();

  bool _savingProfile = false;
  bool _savingPass = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final data = await _api.get('/admin/profile') as Map<String, dynamic>;
    _name.text = data['name']?.toString() ?? '';
    _email.text = data['email']?.toString() ?? '';
    _employeeId.text = data['employee_id']?.toString() ?? '';
    _contact.text = data['contact_number']?.toString() ?? '';
    return data;
  }

  @override
  void dispose() {
    for (final c in [_name, _email, _employeeId, _contact, _current, _newPass]) {
      c.dispose();
    }
    super.dispose();
  }

  void _snack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: error ? Colors.red.shade700 : null));
  }

  Future<void> _saveProfile() async {
    setState(() => _savingProfile = true);
    try {
      await _api.put('/admin/profile', {
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'employee_id': _employeeId.text.trim().isEmpty ? null : _employeeId.text.trim(),
        'contact_number': _contact.text.trim().isEmpty ? null : _contact.text.trim(),
      });
      _snack('Profile updated.');
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _savingProfile = false);
    }
  }

  Future<void> _savePassword() async {
    if (_current.text.isEmpty || _newPass.text.isEmpty) {
      _snack('Enter your current and new password.', error: true);
      return;
    }
    setState(() => _savingPass = true);
    try {
      await _api.put('/admin/profile/password', {
        'current_password': _current.text,
        'password': _newPass.text,
      });
      _current.clear();
      _newPass.clear();
      _snack('Password changed.');
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      if (mounted) setState(() => _savingPass = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snap) {
        if (snap.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snap.hasError) {
          return Center(child: Text(snap.error.toString()));
        }
        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 640),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('Profile',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                          const SizedBox(height: 16),
                          _field(_name, 'Full name'),
                          _field(_email, 'Email'),
                          _field(_employeeId, 'Employee ID'),
                          _field(_contact, 'Contact number'),
                          const SizedBox(height: 8),
                          Align(
                            alignment: Alignment.centerRight,
                            child: FilledButton(
                              onPressed: _savingProfile ? null : _saveProfile,
                              child: _savingProfile
                                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Save profile'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('Change password',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                          const SizedBox(height: 16),
                          _field(_current, 'Current password', obscure: true),
                          _field(_newPass, 'New password (min 8)', obscure: true),
                          const SizedBox(height: 8),
                          Align(
                            alignment: Alignment.centerRight,
                            child: FilledButton(
                              onPressed: _savingPass ? null : _savePassword,
                              child: _savingPass
                                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Text('Change password'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _field(TextEditingController c, String label, {bool obscure = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: c,
        obscureText: obscure,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }
}
