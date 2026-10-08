import 'package:flutter/foundation.dart';
import 'api_client.dart';

class AuthUser {
  final int id;
  final String name;
  final String email;
  final String role;

  AuthUser({required this.id, required this.name, required this.email, required this.role});

  factory AuthUser.fromJson(Map<String, dynamic> json) => AuthUser(
        id: json['id'],
        name: json['name'] ?? '',
        email: json['email'] ?? '',
        role: json['role'] ?? '',
      );
}

class AuthService extends ChangeNotifier {
  final ApiClient _client = ApiClient();

  AuthUser? _user;
  bool _initializing = true;

  AuthUser? get user => _user;
  bool get isLoggedIn => _user != null;
  bool get initializing => _initializing;

  Future<void> restoreSession() async {
    final token = await ApiClient.getToken();
    if (token == null) {
      _initializing = false;
      notifyListeners();
      return;
    }

    try {
      final data = await _client.get('/profile');
      _user = AuthUser.fromJson(data);
    } catch (_) {
      await ApiClient.setToken(null);
      _user = null;
    }

    _initializing = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final data = await _client.post('/login', {
      'email': email,
      'password': password,
      'device_name': 'flutter-mobile',
    });

    await ApiClient.setToken(data['token']);
    _user = AuthUser.fromJson(data['user']);
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await _client.post('/logout', {});
    } catch (_) {
      // Ignore network errors on logout — clear local session regardless.
    }
    await ApiClient.setToken(null);
    _user = null;
    notifyListeners();
  }
}
