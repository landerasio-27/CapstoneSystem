import 'package:flutter/material.dart';

/// Curved green gradient header used on the login screen. Renders a darker
/// translucent wave behind the main gradient wave for depth, with the ISU logo
/// and "ISU INSPECTION" centered above the curve. Respects the status bar.
class WaveHeader extends StatelessWidget {
  final double height;
  final VoidCallback? onSettings;

  const WaveHeader({super.key, required this.height, this.onSettings});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: height,
      child: Stack(
        children: [
          // Back wave (darker, translucent) for depth.
          Positioned.fill(
            child: ClipPath(
              clipper: _BackWaveClipper(),
              child: const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFF14481A), Color(0xFF1B5E20)],
                  ),
                ),
              ),
            ),
          ),

          // Front wave (main brand gradient).
          Positioned.fill(
            child: ClipPath(
              clipper: _FrontWaveClipper(),
              child: const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFF1B5E20), Color(0xFF2E7D32), Color(0xFF43A047)],
                  ),
                ),
              ),
            ),
          ),

          // Logo + title, centered above the curve.
          Positioned.fill(
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 34),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Image.asset('assets/images/logo_round.png', height: 84, width: 84),
                    const SizedBox(height: 12),
                    const Text(
                      'ISU INSPECTION',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 2.2,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Optional settings entry point (top-right), kept discreet.
          if (onSettings != null)
            SafeArea(
              bottom: false,
              child: Align(
                alignment: Alignment.topRight,
                child: IconButton(
                  tooltip: 'Server settings',
                  onPressed: onSettings,
                  icon: const Icon(Icons.dns_outlined, color: Colors.white70, size: 22),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Main wave: gentle S-curve along the bottom.
class _FrontWaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final w = size.width;
    final h = size.height;
    final path = Path()
      ..lineTo(0, h - 48)
      ..quadraticBezierTo(w * 0.25, h, w * 0.5, h - 30)
      ..quadraticBezierTo(w * 0.78, h - 62, w, h - 24)
      ..lineTo(w, 0)
      ..close();
    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) => false;
}

/// Back wave: phase-shifted so it peeks out beneath the front wave.
class _BackWaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    final w = size.width;
    final h = size.height;
    final path = Path()
      ..lineTo(0, h - 26)
      ..quadraticBezierTo(w * 0.28, h - 74, w * 0.55, h - 18)
      ..quadraticBezierTo(w * 0.82, h + 30, w, h - 44)
      ..lineTo(w, 0)
      ..close();
    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) => false;
}
