import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:multicast_dns/multicast_dns.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

/// A Laravel server that passed the discovery handshake.
class DiscoveredServer {
  final String host;
  final int port;
  final String service;
  final String version;

  /// Full base URL, e.g. http://192.168.1.10:8000/api
  final String baseUrl;

  const DiscoveredServer({
    required this.host,
    required this.port,
    required this.service,
    required this.version,
    required this.baseUrl,
  });

  factory DiscoveredServer.fromJson(Map<String, dynamic> json) {
    final port = (json['port'] as num?)?.toInt() ?? 8000;
    return DiscoveredServer(
      host: json['host'] as String? ?? '',
      port: port,
      service: json['service'] as String? ?? 'ISU Supply Management',
      version: json['version'] as String? ?? '1.0',
      baseUrl: ApiClient.normalizeBaseUrl(json['base_url'] as String? ?? ''),
    );
  }

  Map<String, dynamic> toJson() => {
        'host': host,
        'port': port,
        'service': service,
        'version': version,
        'base_url': baseUrl,
      };
}

/// Result of a discovery scan: either a verified server, or diagnostics about
/// what the scan saw — used to give the user an actionable "why" on the
/// not-found screen instead of a dead end.
class DiscoveryOutcome {
  /// The verified ISU server, or null when nothing acceptable was found.
  final DiscoveredServer? server;

  /// Host of a device that answered HTTP but did NOT return the ISU discovery
  /// payload. Surfaces the most common real-world failure: the device is
  /// reachable, but the Laravel app (or its /api/server-discovery route) isn't
  /// running/deployed — e.g. `php artisan serve` without `--host=0.0.0.0`, or
  /// the Pi running an older build.
  final String? otherHttpHost;

  const DiscoveryOutcome(this.server, {this.otherHttpHost});

  bool get sawOtherHttp => otherHttpHost != null;
}

/// Automatic LAN server discovery + verification.
///
/// The Raspberry Pi advertises `_isu-supply._tcp.local` (via avahi). This
/// service browses that mDNS service, collects the candidate host/port pairs,
/// then probes each candidate's `GET /api/server-discovery` endpoint and only
/// accepts one whose JSON identifies the ISU supply management backend. The
/// accepted server is persisted so future launches can connect immediately.
class ServerDiscovery {
  ServerDiscovery._();

  /// mDNS service type advertised by the Pi (see web/deploy/setup-mdns.sh).
  ///
  /// Deliberately NO trailing dot: multicast_dns matches request names
  /// literally against record owner names (it strips one trailing dot from
  /// incoming records), and a dotted request name would never match a
  /// response — discovery would silently find nothing.
  static const String serviceType = '_isu-supply._tcp.local';

  /// The service name the backend must report to be accepted (prevents
  /// connecting to an unrelated device on the LAN).
  static const String expectedService = 'ISU Supply Management';

  static const String _channelName = 'isu_supply/multicast_lock';
  static const Duration scanTimeout = Duration(seconds: 15);
  static const Duration probeTimeout = Duration(seconds: 4);
  static const Duration reachabilityTimeout = Duration(seconds: 3);

  /// Ports the Laravel server may answer on: 8000 = `php artisan serve`,
  /// 80 = Nginx/Apache, 8080 = common alternative. Used by the subnet
  /// fallback scan.
  static const List<int> _httpPorts = [8000, 80, 8080];

  /// Test hook: replaces the live mDNS browse (which needs real sockets) with
  /// a canned candidate list. Never set outside tests.
  @visibleForTesting
  static Future<List<({String host, int port})>> Function()? scannerOverride;

  // ---------------------------------------------------------------------
  //  Discovery flow
  // ---------------------------------------------------------------------

  /// Scans the local network for the ISU server. Returns a [DiscoveryOutcome]
  /// with the verified server, or diagnostics when nothing acceptable found.
  ///
  /// Three strategies, in order:
  ///   0. Configured address — probe the base URL the app already has (manual
  ///      entry from an earlier session, or the emulator's host shortcut).
  ///      If the user has ever typed the server address, reconnect is instant.
  ///   1. mDNS browse — finds a server that *advertises* the service (the
  ///      Raspberry Pi with avahi/Bonjour).
  ///   2. Direct subnet scan — if mDNS found nothing (nothing advertises,
  ///      e.g. a dev PC running Laravel without avahi, or the access point
  ///      blocks multicast), probe every host in the phone's own /24 on the
  ///      usual HTTP ports and accept any that verifies as this backend.
  ///
  /// Any device that answers HTTP without verifying is recorded as
  /// [DiscoveryOutcome.otherHttpHost] so the UI can explain the likely fix.
  ///
  /// [onStatus] receives friendly progress messages for the radar UI.
  static Future<DiscoveryOutcome> scanNetwork({void Function(String)? onStatus}) async {
    onStatus?.call('Searching for ISU Cauayan Server…');

    await acquireMulticastLock();
    try {
      final stub = scannerOverride;

      // Strategy 0 — the address already configured in the app.
      String? otherHost;
      if (stub == null) {
        try {
          onStatus?.call('Checking saved server address…');
          final base = await ApiClient.getBaseUrl();
          final (server, httpAnswered) =
              await _verifyDetailed(base, timeout: const Duration(seconds: 2));
          if (server != null) return DiscoveryOutcome(server);
          if (httpAnswered) {
            otherHost = Uri.parse(ApiClient.normalizeBaseUrl(base)).host;
          }
        } catch (_) {
          // Malformed/no stored URL — continue with network scanning.
        }
      }

      // The mDNS browse has no natural end — bound it so we always fall
      // through instead of hanging forever.
      final candidates = await _findCandidates(onStatus).timeout(
        const Duration(seconds: 8),
        onTimeout: () => const [],
      );
      if (candidates.isNotEmpty) {
        onStatus?.call('Verifying candidate servers…');
        final results = await Future.wait(
          candidates.map((c) => _verifyDetailed('http://${c.host}:${c.port}/api')),
        );
        for (var i = 0; i < results.length; i++) {
          final (server, httpAnswered) = results[i];
          if (server != null) return DiscoveryOutcome(server);
          if (httpAnswered && otherHost == null) otherHost = candidates[i].host;
        }
      }

      if (stub != null) return DiscoveryOutcome(null, otherHttpHost: otherHost);

      // Strategy 2 — subnet sweep.
      onStatus?.call('Checking nearby devices…');
      return await _subnetScanForServer(onStatus, knownOtherHost: otherHost);
    } finally {
      await releaseMulticastLock();
    }
  }

  /// Fallback discovery: quick HTTP probes across the phone's own /24 subnet.
  /// Every probe targets /api/server-discovery, so unrelated devices with web
  /// servers can never be accepted — only the real ISU backend matches.
  static Future<DiscoveryOutcome> _subnetScanForServer(
      void Function(String)? onStatus,
      {String? knownOtherHost}) async {
    final hosts = await _localSubnetHosts();
    if (hosts.isEmpty) {
      return DiscoveryOutcome(null, otherHttpHost: knownOtherHost);
    }

    String? otherHost = knownOtherHost;

    // Probe in waves. Refused/absent hosts fail in milliseconds; only hosts
    // that silently drop packets burn their per-probe timeout, so a real LAN
    // scan normally finishes in a couple of seconds.
    const wave = 12;
    final deadline = DateTime.now().add(const Duration(seconds: 12));
    for (var start = 0; start < hosts.length && DateTime.now().isBefore(deadline);
        start += wave) {
      final slice = hosts.skip(start).take(wave).toList();
      final pairs = <({String host, int port})>[];
      final batch = <Future<(DiscoveredServer?, bool)>>[];
      for (final host in slice) {
        for (final port in _httpPorts) {
          pairs.add((host: host, port: port));
          batch.add(_verifyDetailed('http://$host:$port/api',
              timeout: const Duration(milliseconds: 600)));
        }
      }
      final results = await Future.wait(batch);
      for (var i = 0; i < results.length; i++) {
        final (server, httpAnswered) = results[i];
        if (server != null) return DiscoveryOutcome(server);
        if (httpAnswered && otherHost == null) otherHost = pairs[i].host;
      }
      final done = (start + wave) > hosts.length ? hosts.length : (start + wave);
      onStatus?.call('Checked $done of ${hosts.length} nearby devices…');
    }
    return DiscoveryOutcome(null, otherHttpHost: otherHost);
  }

  /// The phone's private LAN IPv4 address, or null when there is no usable
  /// Wi-Fi/network address. Only private ranges qualify, so we never sweep a
  /// public/ISP range.
  static Future<String?> _privateIPv4() async {
    try {
      final interfaces = await NetworkInterface.list(includeLinkLocal: false);
      for (final iface in interfaces) {
        for (final addr in iface.addresses) {
          if (addr.type != InternetAddressType.IPv4 || addr.isLoopback) continue;
          final parts = addr.address.split('.');
          if (parts.length != 4) continue;
          final b0 = int.tryParse(parts[0]) ?? -1;
          final b1 = int.tryParse(parts[1]) ?? -1;
          final isPrivate = b0 == 10 || (b0 == 172 && b1 >= 16 && b1 <= 31) ||
              (b0 == 192 && b1 == 168);
          if (isPrivate) return addr.address;
        }
      }
    } catch (_) {
      // No network interfaces / unsupported platform → no subnet to scan.
    }
    return null;
  }

  /// The .1–.254 IPv4 hosts of the phone's own subnet.
  /// Empty when there is no usable Wi-Fi address.
  static Future<List<String>> _localSubnetHosts() async {
    final local = await _privateIPv4();
    if (local == null) return const [];

    final lastDot = local.lastIndexOf('.');
    final prefix = local.substring(0, lastDot + 1);
    final ownLast = int.tryParse(local.substring(lastDot + 1)) ?? 0;
    return [
      for (var i = 1; i <= 254; i++)
        if (i != ownLast) '$prefix$i',
    ];
  }

  /// Diagnostics for the discovery UI: the phone's own LAN IP and the subnet
  /// prefix that was scanned (e.g. `192.168.1.x`), so the not-found screen can
  /// show *why* discovery failed (no Wi-Fi vs. wrong network vs. server down).
  static Future<({String? ipv4, String? subnetLabel})> localNetworkInfo() async {
    final ip = await _privateIPv4();
    if (ip == null) return (ipv4: null, subnetLabel: null);
    final lastDot = ip.lastIndexOf('.');
    return (ipv4: ip, subnetLabel: '${ip.substring(0, lastDot + 1)}x');
  }

  /// mDNS browse for [_isu-supply._tcp.local] → SRV records → A records.
  ///
  /// The multicast_dns library keeps every lookup stream open until an
  /// internal 5s timer fires, so a naive nested `await for` (PTR then SRV
  /// then A) serializes each hop into its own ~5s wait and the scan times
  /// out before producing anything. Instead we react to the first record of
  /// each type as it arrives (mDNS responders answer each query within tens
  /// of milliseconds), retrying a couple of times to survive a lost
  /// multicast packet, and stop as soon as one host:port candidate exists.
  static Future<List<({String host, int port})>> _findCandidates(
      void Function(String)? onStatus) async {
    final stub = scannerOverride;
    if (stub != null) return stub();

    final client = MDnsClient();
    final seen = <String>{};
    final candidates = <({String host, int port})>[];
    final budget = DateTime.now().add(const Duration(seconds: 7));

    /// Waits for the first matching record of a query, retrying [attempts]
    /// times if the multicast answer is lost. Returns null when unanswered.
    Future<T?> firstRecord<T extends ResourceRecord>(
      ResourceRecordQuery query, {
      int attempts = 2,
      Duration perAttempt = const Duration(milliseconds: 900),
    }) async {
      for (var i = 0; i < attempts; i++) {
        if (DateTime.now().isAfter(budget)) return null;
        late final StreamSubscription<T> sub;
        final got = Completer<T?>();
        final timer = Timer(perAttempt, () {
          if (!got.isCompleted) got.complete(null);
        });
        sub = client.lookup<T>(query).listen(
          (r) {
            if (!got.isCompleted) got.complete(r);
          },
          onError: (_) {
            if (!got.isCompleted) got.complete(null);
          },
        );
        final record = await got.future;
        timer.cancel();
        await sub.cancel();
        if (record != null) return record;
      }
      return null;
    }

    String trimDot(String name) =>
        name.endsWith('.') ? name.substring(0, name.length - 1) : name;

    try {
      await client.start();
      onStatus?.call('Scanning local network…');

      // 1) PTR browse → the instance(s) advertising _isu-supply._tcp.
      final ptr = await firstRecord<PtrResourceRecord>(
        ResourceRecordQuery.serverPointer(serviceType),
        attempts: 2,
      );
      if (ptr == null) return candidates;

      // 2) SRV for that instance → hostname + HTTP port.
      final srv = await firstRecord<SrvResourceRecord>(
        ResourceRecordQuery.service(trimDot(ptr.domainName)),
        attempts: 2,
        perAttempt: const Duration(milliseconds: 700),
      );
      if (srv == null) return candidates;

      // 3) A record for the hostname → IPv4 address.
      final addr = await firstRecord<IPAddressResourceRecord>(
        ResourceRecordQuery.addressIPv4(trimDot(srv.target)),
        attempts: 3,
        perAttempt: const Duration(milliseconds: 700),
      );
      if (addr == null) return candidates;

      final host = addr.address.address;
      final key = '$host:${srv.port}';
      if (seen.add(key)) {
        candidates.add((host: host, port: srv.port));
      }
    } catch (_) {
      // mDNS failure (no Wi-Fi, no network, unsupported) → no candidates.
    } finally {
      try {
        client.stop();
      } catch (_) {
        // stop() throws StateError if start() failed mid-way — ignore.
      }
    }

    return candidates;
  }

  // ---------------------------------------------------------------------
  //  Verification
  // ---------------------------------------------------------------------

  /// Probes [baseUrl]/server-discovery and returns server info only when the
  /// response proves it is the ISU supply management backend.
  static Future<DiscoveredServer?> verifyAt(String baseUrl,
      {Duration timeout = probeTimeout}) async {
    return (await _verifyDetailed(baseUrl, timeout: timeout)).$1;
  }

  /// Like [verifyAt], but also reports whether *anything* answered HTTP at the
  /// address — the crucial distinction between "nothing there" and "a web
  /// server is there but it isn't the ISU backend" (app not started, bound to
  /// 127.0.0.1, or the discovery route not deployed).
  static Future<(DiscoveredServer?, bool httpAnswered)> _verifyDetailed(
      String baseUrl,
      {Duration timeout = probeTimeout}) async {
    final url = '${ApiClient.normalizeBaseUrl(baseUrl)}/server-discovery';
    bool answered = false;
    try {
      final res = await http.get(Uri.parse(url)).timeout(timeout);
      answered = true; // something served HTTP here, whatever the body is
      if (res.statusCode == 200) {
        try {
          final json = jsonDecode(res.body);
          if (json is Map<String, dynamic> &&
              json['success'] == true &&
              (json['service'] == expectedService ||
                  json['service_key'] == 'isu-supply')) {
            final base = ApiClient.normalizeBaseUrl(baseUrl);
            final uri = Uri.parse(base);
            return (
              DiscoveredServer(
                host: uri.host,
                port: uri.port,
                service: json['service'] as String? ?? expectedService,
                version: json['version'] as String? ?? '1.0',
                baseUrl: base,
              ),
              true,
            );
          }
        } catch (_) {
          // Non-JSON body (router/admin page etc.) — not the ISU backend,
          // but the device definitely answered.
        }
      }
      return (null, true);
    } catch (_) {
      return (null, answered);
    }
  }

  /// True when the saved server still answers the discovery handshake.
  static Future<bool> isSavedServerReachable() async {
    final saved = await savedInfo();
    if (saved == null) return false;
    return (await verifyAt(saved.baseUrl, timeout: reachabilityTimeout)) != null;
  }

  // ---------------------------------------------------------------------
  //  Persistence
  // ---------------------------------------------------------------------

  /// Persists the discovered server as the app's API base URL.
  static Future<void> save(DiscoveredServer server) async {
    await ApiClient.setBaseUrl(server.baseUrl);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('server_info', jsonEncode(server.toJson()));
    await prefs.setString('server_saved_at', DateTime.now().toIso8601String());
  }

  /// Info about the currently saved server, or null when none is saved.
  static Future<DiscoveredServer?> savedInfo() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('server_info');
    if (raw == null) return null;
    try {
      return DiscoveredServer.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  /// When the saved server was discovered (for the settings card).
  static Future<DateTime?> savedAt() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('server_saved_at');
    return raw == null ? null : DateTime.tryParse(raw);
  }

  // ---------------------------------------------------------------------
  //  Android multicast lock (best-effort)
  // ---------------------------------------------------------------------

  /// Holds Android's Wi-Fi multicast lock while scanning so mDNS responses
  /// are received reliably. No-op (or fails silently) off Android / without
  /// the platform channel — discovery still attempts to run either way.
  ///
  /// Both calls are time-boxed: if the platform side never answers (or doesn't
  /// exist), discovery must fall through instead of hanging.
  static Future<void> acquireMulticastLock() async {
    try {
      await const MethodChannel(_channelName).invokeMethod('acquire').timeout(
            const Duration(seconds: 2),
            onTimeout: () => null,
          );
    } catch (_) {
      // Channel missing (non-Android) or permission issue — degrade gracefully.
    }
  }

  static Future<void> releaseMulticastLock() async {
    try {
      await const MethodChannel(_channelName).invokeMethod('release').timeout(
            const Duration(seconds: 2),
            onTimeout: () => null,
          );
    } catch (_) {
      // Ignore — nothing to release.
    }
  }
}