import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../services/server_discovery.dart';
import '../theme/app_theme.dart';
import '../widgets/manual_server_dialog.dart';

enum _DiscoveryPhase { scanning, found, notFound }

/// Radar-style automatic server discovery.
///
/// Scans the local network for the ISU supply management server (configured
/// address → mDNS `_isu-supply._tcp.local` → subnet sweep), verifies it, and
/// saves it as the API base URL. On success the screen reports back via
/// [onConnected] (when embedded) or by popping with `true` (when pushed as a
/// route).
class ServerDiscoveryScreen extends StatefulWidget {
  final VoidCallback? onConnected;

  const ServerDiscoveryScreen({super.key, this.onConnected});

  @override
  State<ServerDiscoveryScreen> createState() => _ServerDiscoveryScreenState();
}

class _ServerDiscoveryScreenState extends State<ServerDiscoveryScreen>
    with TickerProviderStateMixin {
  static const _scanMessages = [
    'Searching for ISU Cauayan Server…',
    'Scanning local network…',
    'Looking for the Raspberry Pi…',
    'Checking nearby devices…',
  ];

  // The radar controllers repeat forever, across every phase — pausing them
  // mid-sweep freezes the beam at an odd angle and restarting it snaps. The
  // not-found phase just dims the radar (animated opacity) while the sweep
  // keeps gliding underneath.
  late final AnimationController _sweep =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 3200))
        ..repeat();
  late final AnimationController _pulse =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1600))
        ..repeat();
  late final Listenable _radarAnimation = Listenable.merge([_sweep, _pulse]);

  bool _deactivated = false;

  _DiscoveryPhase _phase = _DiscoveryPhase.scanning;
  String _status = _scanMessages.first;
  DiscoveredServer? _server;
  DiscoveryOutcome? _outcome;
  Timer? _statusTicker;
  int _messageIndex = 0;
  bool _scanning = false;

  @override
  void initState() {
    super.initState();
    _startScan();
  }

  @override
  void deactivate() {
    _statusTicker?.cancel();
    super.deactivate();
  }

  @override
  void dispose() {
    _deactivated = true;
    _sweep.dispose();
    _pulse.dispose();
    super.dispose();
  }

  void _startScan() {
    _statusTicker?.cancel();
    _sweep.repeat(); // resume the beam if a previous scan froze it
    _pulse.repeat();
    setState(() {
      _phase = _DiscoveryPhase.scanning;
      _server = null;
      _outcome = null;
      _status = _scanMessages.first;
      _messageIndex = 0;
    });

    // Rotate through friendly status lines while the network scan runs.
    _statusTicker = Timer.periodic(const Duration(milliseconds: 1800), (_) {
      if (_deactivated || !mounted || _phase != _DiscoveryPhase.scanning) return;
      setState(() {
        _messageIndex = (_messageIndex + 1) % _scanMessages.length;
        _status = _scanMessages[_messageIndex];
      });
    });

    _scan();
  }

  Future<void> _scan() async {
    if (_scanning) return;
    _scanning = true;
    try {
      final outcome = await ServerDiscovery.scanNetwork(
        onStatus: (s) {
          if (!_deactivated && mounted && _phase == _DiscoveryPhase.scanning) {
            setState(() => _status = s);
          }
        },
      );
      if (!mounted) return;

      _statusTicker?.cancel();
      setState(() {
        _outcome = outcome;
        _server = outcome.server;
        _phase = outcome.server != null
            ? _DiscoveryPhase.found
            : _DiscoveryPhase.notFound;
      });
      if (outcome.server != null) {
        await ServerDiscovery.save(outcome.server!);
      }
      _freezeRadarSoon();
    } catch (_) {
      // Discovery itself should never throw, but never leave the screen stuck
      // on "scanning" if it does.
      if (!mounted) return;
      _statusTicker?.cancel();
      setState(() {
        _outcome = const DiscoveryOutcome(null);
        _server = null;
        _phase = _DiscoveryPhase.notFound;
      });
      _freezeRadarSoon();
    } finally {
      _scanning = false;
    }
  }

  /// Lets the phase transition play out (fade/scale + radar dim) with the
  /// beam still gliding, then stops the radar controllers so the screen
  /// doesn't burn frames while the user reads it. "Scan Again" restarts them.
  void _freezeRadarSoon() {
    Future.delayed(const Duration(milliseconds: 900), () {
      if (mounted && _phase != _DiscoveryPhase.scanning) {
        _sweep.stop();
        _pulse.stop();
      }
    });
  }

  void _continue() {
    if (widget.onConnected != null) {
      widget.onConnected!();
    } else {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surfaceTint,
      appBar: AppBar(title: const Text('Connect to Server')),
      body: SafeArea(
        top: false,
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 450),
              switchInCurve: Curves.easeOutCubic,
              switchOutCurve: Curves.easeInCubic,
              transitionBuilder: (child, animation) => FadeTransition(
                opacity: animation,
                child: ScaleTransition(
                  scale: Tween<double>(begin: 0.96, end: 1).animate(animation),
                  child: child,
                ),
              ),
              child: _phase == _DiscoveryPhase.scanning
                  ? _buildScanning(key: const ValueKey('scanning'))
                  : _phase == _DiscoveryPhase.found
                      ? _buildFound(key: const ValueKey('found'))
                      : _buildNotFound(key: const ValueKey('notFound')),
            ),
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------
  //  Scanning state
  // ---------------------------------------------------------------------

  Widget _buildScanning({Key? key}) {
    return Column(
      key: key,
      children: [
        _Radar(dimmed: false),
        const SizedBox(height: 28),
        AnimatedSwitcher(
          duration: const Duration(milliseconds: 350),
          switchInCurve: Curves.easeOut,
          switchOutCurve: Curves.easeIn,
          transitionBuilder: (child, animation) => FadeTransition(
            opacity: animation,
            child: SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0, 0.25),
                end: Offset.zero,
              ).animate(animation),
              child: child,
            ),
          ),
          child: Text(
            _status,
            key: ValueKey(_status),
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: AppColors.textPrimary,
            ),
          ),
        ),
        const SizedBox(height: 10),
        const Text(
          'Make sure you are connected to the campus Wi-Fi and the '
          'server is turned on.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
        ),
        const SizedBox(height: 28),
      ],
    );
  }

  // ---------------------------------------------------------------------
  //  Success state
  // ---------------------------------------------------------------------

  Widget _buildFound({Key? key}) {
    final server = _server!;
    return Column(
      key: key,
      children: [
        Container(
          width: 88,
          height: 88,
          decoration: BoxDecoration(
            color: AppColors.verified.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.check_rounded, color: AppColors.verified, size: 52),
        ),
        const SizedBox(height: 16),
        const Text(
          'Server Found',
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.verified),
        ),
        const SizedBox(height: 20),
        AppCard(
          padding: const EdgeInsets.all(18),
          child: Column(
            children: [
              Row(
                children: [
                  const Icon(Icons.dns_outlined, color: AppColors.primary, size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          server.service,
                          style: const TextStyle(
                              fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                        ),
                        const SizedBox(height: 2),
                        const Text('Raspberry Pi Server',
                            style: TextStyle(fontSize: 12.5, color: AppColors.textSecondary)),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Server address',
                      style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                  Text(
                    server.host,
                    style: const TextStyle(
                        fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Status',
                      style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.verified.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.circle, color: AppColors.verified, size: 8),
                        SizedBox(width: 6),
                        Text('Connected',
                            style: TextStyle(
                                fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.verified)),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        FilledButton.icon(
          onPressed: _continue,
          icon: const Icon(Icons.arrow_forward_rounded),
          label: const Text('Continue'),
        ),
        const SizedBox(height: 12),
      ],
    );
  }

  // ---------------------------------------------------------------------
  //  Not-found state
  // ---------------------------------------------------------------------

  Widget _buildNotFound({Key? key}) {
    final outcome = _outcome;
    final sawOtherHttp = outcome?.sawOtherHttp ?? false;
    return FutureBuilder<({String? ipv4, String? subnetLabel})>(
      key: key,
      future: ServerDiscovery.localNetworkInfo(),
      builder: (context, snap) {
        final subnet = snap.data?.subnetLabel;
        final hasWifi = snap.data?.ipv4 != null;
        return Column(
          children: [
            _Radar(dimmed: true),
            const SizedBox(height: 20),
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.misplaced.withValues(alpha: 0.14),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.wifi_off_rounded, color: AppColors.misplaced, size: 34),
            ),
            const SizedBox(height: 14),
            const Text(
              'Server not found',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 8),
            Text(
              _notFoundExplanation(hasWifi, subnet, sawOtherHttp),
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 13.5, height: 1.4, color: AppColors.textSecondary),
            ),
            if (sawOtherHttp && outcome?.otherHttpHost != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.misplaced.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.info_outline_rounded,
                        size: 18, color: AppColors.misplaced),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        'A device answered at ${outcome!.otherHttpHost} '
                        'but it is not running the ISU server.',
                        style: const TextStyle(
                            fontSize: 12.5, height: 1.35, color: AppColors.textPrimary),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 26),
            FilledButton.icon(
              onPressed: _startScan,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Scan Again'),
            ),
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () async {
                final ok = await showManualServerDialog(context);
                if (ok == true && mounted) _continue();
              },
              icon: const Icon(Icons.keyboard_rounded, size: 20),
              label: const Text('Enter Server Manually'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.textSecondary,
                side: const BorderSide(color: AppColors.border),
              ),
            ),
            const SizedBox(height: 12),
          ],
        );
      },
    );
  }

  /// The most likely reason discovery failed, given what the scan observed.
  String _notFoundExplanation(bool hasWifi, String? subnet, bool sawOtherHttp) {
    if (!hasWifi) {
      return 'Your phone doesn\u2019t seem to be connected to a Wi-Fi '
          'network.\nConnect to the campus Wi-Fi and scan again.';
    }
    if (sawOtherHttp) {
      return 'Devices answered on $subnet, but none of them is the ISU '
          'server.\nMake sure the server app is running '
          '(php artisan serve --host=0.0.0.0) and restarted.';
    }
    return 'Nothing answered on $subnet. Make sure the server is turned on, '
          'and that your phone is on the same Wi-Fi network as the server.';
  }
}

// -------------------------------------------------------------------------
//  Radar visual
// -------------------------------------------------------------------------

class _Radar extends StatelessWidget {
  final bool dimmed;

  const _Radar({required this.dimmed});

  @override
  Widget build(BuildContext context) {
    final state = context.findAncestorStateOfType<_ServerDiscoveryScreenState>()!;
    return SizedBox(
      width: 272,
      height: 272,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned.fill(
            child: AnimatedOpacity(
              duration: const Duration(milliseconds: 600),
              curve: Curves.easeInOut,
              opacity: dimmed ? 0.35 : 1,
              // Repaint boundary: the radar canvas repaints on every frame of
              // the sweep, but the seal image on top must NOT — it is a static
              // asset and repainting it 60×/s wastes GPU time.
              child: RepaintBoundary(
                child: AnimatedBuilder(
                  animation: state._radarAnimation,
                  builder: (context, _) => CustomPaint(
                    painter: _RadarPainter(
                      sweepAngle: state._sweep.value * 2 * math.pi,
                      pulseT: state._pulse.value,
                    ),
                  ),
                ),
              ),
            ),
          ),
          // Brand seal at the centre of the radar — the artwork has transparent
          // corners so it sits directly on the radar, no white disc behind it.
          SizedBox(
            width: 150,
            height: 150,
            child: Image.asset(
              'assets/images/logo_round.png',
              fit: BoxFit.contain,
              errorBuilder: (_, _, _) => Icon(Icons.dns_rounded,
                  color: AppColors.primary, size: 56),
            ),
          ),
        ],
      ),
    );
  }
}

class _RadarPainter extends CustomPainter {
  final double sweepAngle;
  final double pulseT;

  /// Deterministic blips so they don't jump around between frames.
  final List<({double angle, double frac, double alpha})> _blips;

  _RadarPainter({
    required this.sweepAngle,
    required this.pulseT,
  }) : _blips = _buildBlips();

  static List<({double angle, double frac, double alpha})> _buildBlips() {
    final rand = math.Random(2024);
    return List.generate(
      16,
      (_) => (
        angle: rand.nextDouble() * 2 * math.pi,
        frac: 0.16 + rand.nextDouble() * 0.62,
        alpha: 0.22 + rand.nextDouble() * 0.45,
      ),
    );
  }

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.width / 2 - 6;
    final primary = AppColors.primary;

    // Ease the pulse so rings expand and contract smoothly (sinusoidal feel).
    final pt = Curves.easeInOut.transform(pulseT);

    // Background disc.
    canvas.drawCircle(
      center,
      radius,
      Paint()..color = primary.withValues(alpha: 0.07),
    );

    // Concentric rings — the inner rings breathe gently with the pulse so the
    // whole radar feels alive instead of static, while the outer ring stays
    // fixed as the container edge.
    final ringPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;
    for (final f in [0.25, 0.5, 0.75]) {
      final breathe = 1 + 0.02 * math.sin((pt * 2 - f) * math.pi);
      ringPaint.color = primary.withValues(alpha: 0.28 * (0.9 + 0.1 * breathe));
      canvas.drawCircle(center, radius * f * breathe, ringPaint);
    }
    ringPaint.color = primary.withValues(alpha: 0.28);
    canvas.drawCircle(center, radius, ringPaint);

    final crossPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1
      ..color = primary.withValues(alpha: 0.16);
    canvas.drawLine(Offset(center.dx - radius, center.dy), Offset(center.dx + radius, center.dy), crossPaint);
    canvas.drawLine(Offset(center.dx, center.dy - radius), Offset(center.dx, center.dy + radius), crossPaint);

    // Blips — brightness follows the rotating beam, like a real radar: a blip
    // lights up as the sweep passes over it and decays until the beam returns.
    for (final b in _blips) {
      // 0..1 = how recently the beam crossed this blip.
      final sinceSweep = (sweepAngle - b.angle) % (2 * math.pi);
      final hit = (1 - sinceSweep / (2 * math.pi)).clamp(0.0, 1.0);
      // Ease the decay so it glows right after the pass and fades smoothly.
      final glow = 0.35 + 0.65 * Curves.easeOut.transform(hit);
      canvas.drawCircle(
        center + Offset(math.cos(b.angle), math.sin(b.angle)) * (radius * b.frac),
        2.8 + 1.4 * glow,
        Paint()..color = primary.withValues(alpha: b.alpha * glow),
      );
    }

    // Expanding pulse rings — two staggered rings ripple continuously so
    // there is never a dead moment. The triangle fold makes each ring expand
    // to the rim and flow back in; a sawtooth restart would visibly pop.
    for (final offset in [0.0, 0.5]) {
      final raw = (pt + offset) % 1.0;
      final t = 1 - (2 * raw - 1).abs();
      final pr = radius * (0.3 + 0.62 * t);
      canvas.drawCircle(
        center,
        pr,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.2 * (1 - t)
          ..color = primary.withValues(alpha: (1 - t) * 0.40),
      );
    }

    // Rotating sweep — beam with a soft trailing fade.
    canvas.save();
    canvas.translate(center.dx, center.dy);
    canvas.rotate(sweepAngle);
    final sweepRect = Rect.fromCircle(center: Offset.zero, radius: radius);
    final shader = SweepGradient(
      startAngle: 2 * math.pi - 1.9,
      endAngle: 2 * math.pi,
      colors: [
        primary.withValues(alpha: 0),
        primary.withValues(alpha: 0.38),
      ],
      // Concentrate the fade near the leading edge so the trail stays lit.
      stops: const [0.0, 0.85],
    ).createShader(sweepRect);
    canvas.drawArc(sweepRect, 0, 2 * math.pi, false, Paint()..shader = shader);
    // Bright leading edge line.
    canvas.drawLine(
      Offset.zero,
      Offset(radius * math.cos(0), radius * math.sin(0)),
      Paint()
        ..strokeWidth = 2
        ..color = primary.withValues(alpha: 0.55)
        ..strokeCap = StrokeCap.round,
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(_RadarPainter old) =>
      old.sweepAngle != sweepAngle || old.pulseT != pulseT;
}
