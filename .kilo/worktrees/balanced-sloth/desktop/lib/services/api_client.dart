import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Friendly, user-facing error for anything that goes wrong talking to the API.
class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

/// Thin HTTP wrapper around the Laravel admin API (backend lives in ../web).
/// Handles the configurable base URL and the Sanctum bearer token, and maps
/// the `{status, message, data}` envelope down to `data`.
class ApiClient {
  static const _baseUrlKey = 'api_base_url';
  static const _tokenKey = 'auth_token';

  // The desktop app usually runs on the same PC as the backend.
  static const defaultBaseUrl = 'http://127.0.0.1:8000/api';

  /// Normalizes a user-entered address: adds a scheme, forces http (the backend
  /// is plain HTTP on the LAN), strips trailing slashes and appends `/api`.
  static String normalizeBaseUrl(String url) {
    var u = url.trim();
    if (u.isEmpty) return defaultBaseUrl;
    if (!u.startsWith('http://') && !u.startsWith('https://')) u = 'http://$u';
    if (u.startsWith('https://')) u = u.replaceFirst('https://', 'http://');
    u = u.replaceAll(RegExp(r'/+$'), '');
    if (!RegExp(r'/api(/|$)').hasMatch(u)) u = '$u/api';
    return u;
  }

  static Future<String> getBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    return normalizeBaseUrl(prefs.getString(_baseUrlKey) ?? defaultBaseUrl);
  }

  static Future<void> setBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_baseUrlKey, normalizeBaseUrl(url));
  }

  static Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenKey);
  }

  static Future<void> setToken(String? token) async {
    final prefs = await SharedPreferences.getInstance();
    if (token == null) {
      await prefs.remove(_tokenKey);
    } else {
      await prefs.setString(_tokenKey, token);
    }
  }

  Future<Map<String, String>> _headers() async {
    final token = await getToken();
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  Future<dynamic> get(String path) => _send((base, headers) =>
      http.get(Uri.parse('$base$path'), headers: headers));

  Future<dynamic> post(String path, [Map<String, dynamic>? body]) => _send((base, headers) =>
      http.post(Uri.parse('$base$path'), headers: headers, body: jsonEncode(body ?? {})));

  Future<dynamic> put(String path, [Map<String, dynamic>? body]) => _send((base, headers) =>
      http.put(Uri.parse('$base$path'), headers: headers, body: jsonEncode(body ?? {})));

  Future<dynamic> patch(String path, [Map<String, dynamic>? body]) => _send((base, headers) =>
      http.patch(Uri.parse('$base$path'), headers: headers, body: jsonEncode(body ?? {})));

  Future<dynamic> delete(String path) => _send((base, headers) =>
      http.delete(Uri.parse('$base$path'), headers: headers));

  /// Fetches a raw binary body (e.g. a PDF stream) with the bearer token.
  Future<List<int>> getBytes(String path) async {
    final base = await getBaseUrl();
    final token = await getToken();
    final res = await http.get(Uri.parse('$base$path'), headers: {
      'Accept': '*/*',
      if (token != null) 'Authorization': 'Bearer $token',
    }).timeout(const Duration(seconds: 30));
    if (res.statusCode >= 200 && res.statusCode < 300) return res.bodyBytes;
    throw ApiException('Failed to download file (HTTP ${res.statusCode}).', statusCode: res.statusCode);
  }

  Future<dynamic> _send(
    Future<http.Response> Function(String base, Map<String, String> headers) request,
  ) async {
    try {
      final base = await getBaseUrl();
      final headers = await _headers();
      final response = await request(base, headers).timeout(const Duration(seconds: 20));
      return _handle(response);
    } on HandshakeException {
      throw ApiException('Secure (HTTPS) connection failed — the server uses plain HTTP. '
          'Check the server address in Settings starts with "http://".');
    } on SocketException {
      throw ApiException('Cannot reach the server. Make sure the backend is running '
          '(Start ISU Server) and the address in Settings is correct.');
    } on TimeoutException {
      throw ApiException('The server took too long to respond. Please try again.');
    } on http.ClientException catch (e) {
      throw ApiException('Network error: ${e.message}');
    } on FormatException {
      throw ApiException('Received an invalid response from the server.');
    }
  }

  dynamic _handle(http.Response response) {
    Map<String, dynamic> decoded;
    try {
      decoded = jsonDecode(response.body) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException('Unexpected server response (HTTP ${response.statusCode}). '
          'Make sure the address in Settings points to the API (…:8000/api).',
          statusCode: response.statusCode);
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded['data'];
    }

    if (response.statusCode == 422 && decoded['errors'] != null) {
      final errors = (decoded['errors'] as Map<String, dynamic>)
          .values
          .expand((v) => (v as List).map((e) => e.toString()))
          .join('\n');
      throw ApiException(errors.isNotEmpty ? errors : (decoded['message'] ?? 'Validation error.'),
          statusCode: 422);
    }

    throw ApiException(decoded['message'] ?? 'Something went wrong (HTTP ${response.statusCode}).',
        statusCode: response.statusCode);
  }
}
