import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:isu_supply_inspector/services/server_discovery.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  late HttpServer server;
  late int port;

  Future<HttpServer> startServer(Map<String, dynamic> body, {int status = 200}) async {
    final s = await HttpServer.bind(InternetAddress.loopbackIPv4, 0);
    port = s.port;
    s.listen((req) {
      if (req.uri.path == '/api/server-discovery') {
        req.response
          ..statusCode = status
          ..headers.contentType = ContentType.json
          ..write(jsonEncode(body));
      } else {
        req.response.statusCode = 404;
      }
      req.response.close();
    });
    return s;
  }

  tearDown(() async {
    await server.close(force: true);
  });

  test('verifyAt accepts the real ISU backend payload', () async {
    server = await startServer({
      'success': true,
      'service': 'ISU Supply Management',
      'service_key': 'isu-supply',
      'version': '1.0',
      'api': '/api',
    });

    final found = await ServerDiscovery.verifyAt('http://127.0.0.1:$port/api');
    expect(found, isNotNull);
    expect(found!.service, 'ISU Supply Management');
    expect(found.version, '1.0');
    expect(found.host, '127.0.0.1');
    expect(found.baseUrl, 'http://127.0.0.1:$port/api');
  });

  test('verifyAt rejects an unrelated device', () async {
    server = await startServer({
      'success': true,
      'service': 'Some Other Thing',
      'version': '9.9',
    });

    final found = await ServerDiscovery.verifyAt('http://127.0.0.1:$port/api');
    expect(found, isNull);
  });

  test('verifyAt rejects non-JSON / error responses', () async {
    server = await startServer({}, status: 500);
    final found = await ServerDiscovery.verifyAt('http://127.0.0.1:$port/api');
    expect(found, isNull);
  });

  test('verifyAt rejects an unreachable address', () async {
    // Bind a server, note the port, then close it so nothing answers.
    server = await startServer({'success': true});
    final deadPort = port;
    await server.close(force: true);

    final found = await ServerDiscovery.verifyAt(
      'http://127.0.0.1:$deadPort/api',
      timeout: const Duration(milliseconds: 800),
    );
    expect(found, isNull);
  });

  test('saved server round-trips through persistence', () async {
    SharedPreferences.setMockInitialValues({});

    const serverInfo = DiscoveredServer(
      host: '192.168.1.50',
      port: 8000,
      service: 'ISU Supply Management',
      version: '1.0',
      baseUrl: 'http://192.168.1.50:8000/api',
    );

    await ServerDiscovery.save(serverInfo);
    final restored = await ServerDiscovery.savedInfo();
    expect(restored, isNotNull);
    expect(restored!.host, '192.168.1.50');
    expect(restored.port, 8000);
    expect(restored.baseUrl, 'http://192.168.1.50:8000/api');

    // Reset for other tests / runs.
    SharedPreferences.setMockInitialValues({});
  });
}
