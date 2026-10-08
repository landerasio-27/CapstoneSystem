import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'api_client.dart';

/// The signed-in user (as returned by POST /api/login).
class AuthUser {
  final int id;
  final String name;
  final String email;
  final String? role;

  AuthUser({required this.id, required this.name, required this.email, this.role});

  bool get isAdmin => role == 'admin';

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
        role: json['role'] as String?,
      );

  Map<String, dynamic> toJson() => {'id': id, 'name': name, 'email': email, 'role': role};
}

/// Authentication + session for the desktop admin client.
class AuthService {
  AuthService._();
  static final AuthService instance = AuthService._();

  static const _userKey = 'auth_user';
  final _api = ApiClient();
  AuthUser? _user;

  AuthUser? get user => _user;

  /// Restores a saved session on app start. Returns true if a valid admin
  /// session exists (token + cached admin user).
  Future<bool> restore() async {
    final token = await ApiClient.getToken();
    if (token == null) return false;
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_userKey);
    if (raw == null) return false;
    try {
      _user = AuthUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return false;
    }
    return _user?.isAdmin ?? false;
  }

  /// Logs in and rejects non-admin accounts (this is the admin desktop client).
  Future<AuthUser> login(String email, String password) async {
    final data = await _api.post('/login', {
      'email': email.trim(),
      'password': password,
      'device_name': 'desktop-admin',
    });

    final token = data['token'] as String?;
    final user = AuthUser.fromJson(data['user'] as Map<String, dynamic>);

    if (!user.isAdmin) {
      throw ApiException('This app is for supply-office administrators only. '
          'Your account role is "${user.role ?? 'unknown'}".');
    }

    await ApiClient.setToken(token);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userKey, jsonEncode(user.toJson()));
    _user = user;
    return user;
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } catch (_) {
      // Even if the server call fails, clear the local session.
    }
    await ApiClient.setToken(null);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_userKey);
    _user = null;
  }
}
