import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiException implements Exception {
  final String message;
  ApiException(this.message);

  @override
  String toString() => message;
}

/// Thin HTTP wrapper around the Laravel REST API. Handles the base URL
/// (configurable, since the server runs on a local Raspberry Pi whose LAN IP
/// varies per deployment) and the Sanctum bearer token.
class ApiClient {
  static const _baseUrlKey = 'api_base_url';
  static const _tokenKey = 'auth_token';
  static const defaultBaseUrl = 'http://10.0.2.2:8000/api';

  /// The backend is served over plain HTTP on the LAN (Raspberry Pi / dev
  /// server) — there is no TLS anywhere. So we normalize any saved address to
  /// guarantee we never attempt an HTTPS/TLS handshake (which fails with a
  /// HandshakeException against a plain-HTTP server). We also add a missing
  /// scheme / the `/api` prefix so common typos still work.
  static String normalizeBaseUrl(String url) {
    var u = url.trim();
    if (u.isEmpty) return defaultBaseUrl;

    // Add a scheme if the user omitted it (e.g. "192.168.1.10:8000/api").
    if (!u.startsWith('http://') && !u.startsWith('https://')) {
      u = 'http://$u';
    }

    // Force HTTP — the server does not serve HTTPS, so https:// would throw a
    // HandshakeException. Downgrade it instead of failing.
    if (u.startsWith('https://')) {
      u = u.replaceFirst('https://', 'http://');
    }

    // Drop any trailing slashes so "$base$path" doesn't double up.
    u = u.replaceAll(RegExp(r'/+$'), '');

    // The app only ever calls /api/* endpoints; append it if it's missing.
    if (!RegExp(r'/api(/|$)').hasMatch(u)) {
      u = '$u/api';
    }

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

  Future<dynamic> get(String path) async {
    return _send(() async {
      final baseUrl = await getBaseUrl();
      return http
          .get(Uri.parse('$baseUrl$path'), headers: await _headers())
          .timeout(const Duration(seconds: 15));
    });
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    return _send(() async {
      final baseUrl = await getBaseUrl();
      return http
          .post(Uri.parse('$baseUrl$path'), headers: await _headers(), body: jsonEncode(body))
          .timeout(const Duration(seconds: 15));
    });
  }

  /// Runs a request and turns low-level network failures into friendly,
  /// actionable messages instead of raw exceptions surfacing in the UI.
  Future<dynamic> _send(Future<http.Response> Function() request) async {
    try {
      return _handle(await request());
    } on HandshakeException {
      throw ApiException(
        'Secure (HTTPS) connection failed. The server uses plain HTTP — '
        'open Server Settings and make sure the address starts with "http://".',
      );
    } on SocketException {
      throw ApiException(
        'Cannot reach the server. Check that the server is running and that the '
        'address in Server Settings is correct and on the same network.',
      );
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
          'Make sure the Server Settings address points to the API (…:8000/api).');
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded['data'];
    }

    if (response.statusCode == 422 && decoded['errors'] != null) {
      final errors = (decoded['errors'] as Map<String, dynamic>)
          .values
          .expand((v) => (v as List).map((e) => e.toString()))
          .join('\n');
      throw ApiException(errors.isNotEmpty ? errors : (decoded['message'] ?? 'Validation error.'));
    }

    throw ApiException(decoded['message'] ?? 'Something went wrong (HTTP ${response.statusCode}).');
  }
}
