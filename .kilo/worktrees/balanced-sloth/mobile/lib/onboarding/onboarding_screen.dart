import 'package:flutter/material.dart';
import '../theme/app_theme.dart';
import '../screens/login_gate.dart';
import 'onboarding_prefs.dart';

/// Full-screen swipeable 3-image onboarding slideshow shown once after the
/// splash on first launch. Tapping "Get Started" goes to the login screen.
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _controller = PageController();
  int _page = 0;

  static const List<String> _images = [
    'assets/images/slideshow-1.png',
    'assets/images/slideshow-2.png',
    'assets/images/slideshow-3.png',
  ];

  bool get _isLast => _page == _images.length - 1;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await OnboardingPrefs.markComplete();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginGate()),
    );
  }

  void _skipToEnd() {
    _controller.animateToPage(
      _images.length - 1,
      duration: const Duration(milliseconds: 450),
      curve: Curves.easeInOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          // Full-screen slides.
          PageView.builder(
            controller: _controller,
            itemCount: _images.length,
            onPageChanged: (i) => setState(() => _page = i),
            itemBuilder: (context, i) => Image.asset(
              _images[i],
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
              errorBuilder: (_, _, _) => ColoredBox(
                color: AppColors.background,
                child: Center(child: _MissingImage(index: i + 1)),
              ),
            ),
          ),

          // Bottom scrim so the dots / button stay readable over any image.
          const Align(
            alignment: Alignment.bottomCenter,
            child: IgnorePointer(
              child: SizedBox(
                height: 280,
                width: double.infinity,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black54],
                    ),
                  ),
                ),
              ),
            ),
          ),

          // Skip (top-right) on slides 1 & 2.
          SafeArea(
            child: Align(
              alignment: Alignment.topRight,
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: AnimatedOpacity(
                  opacity: _isLast ? 0 : 1,
                  duration: const Duration(milliseconds: 200),
                  child: Material(
                    color: Colors.black.withValues(alpha: 0.28),
                    borderRadius: BorderRadius.circular(20),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(20),
                      onTap: _isLast ? null : _skipToEnd,
                      child: const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        child: Text('Skip', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),

          // Bottom controls: dots + Get Started.
          Align(
            alignment: Alignment.bottomCenter,
            child: SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(_images.length, (i) {
                        final active = i == _page;
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 250),
                          curve: Curves.easeOut,
                          margin: const EdgeInsets.symmetric(horizontal: 4),
                          height: 8,
                          width: active ? 26 : 8,
                          decoration: BoxDecoration(
                            color: active ? AppColors.primary : Colors.white.withValues(alpha: 0.65),
                            borderRadius: BorderRadius.circular(8),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 20),
                    // Get Started (last slide only) — fades/scales in and always
                    // reserves its height so the layout doesn't jump.
                    AnimatedOpacity(
                      opacity: _isLast ? 1 : 0,
                      duration: const Duration(milliseconds: 300),
                      child: AnimatedScale(
                        scale: _isLast ? 1 : 0.92,
                        duration: const Duration(milliseconds: 300),
                        curve: Curves.easeOut,
                        child: IgnorePointer(
                          ignoring: !_isLast,
                          child: SizedBox(
                            width: double.infinity,
                            child: FilledButton(
                              onPressed: _finish,
                              style: FilledButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                minimumSize: const Size.fromHeight(54),
                                elevation: 3,
                                shadowColor: AppColors.primary.withValues(alpha: 0.5),
                                textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                              ),
                              child: const Text('Get Started'),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Friendly fallback if a slideshow asset hasn't been dropped in yet.
class _MissingImage extends StatelessWidget {
  final int index;
  const _MissingImage({required this.index});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.image_outlined, size: 72, color: AppColors.textSecondary),
        const SizedBox(height: 12),
        Text('slideshow-$index.png',
            style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w600)),
        const SizedBox(height: 4),
        Text('Drop this image into assets/images/',
            style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
      ],
    );
  }
}
