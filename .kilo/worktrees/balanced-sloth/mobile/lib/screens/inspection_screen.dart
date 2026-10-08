import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../models/inspection.dart';
import '../services/inspection_service.dart';
import '../theme/app_theme.dart';
import 'main_scaffold.dart';

/// Continuous QR inspection scanner. The camera stays open after every scan;
/// each result shows a fast, non-blocking coloured popup and updates a live
/// counter. When all assigned items are verified it prompts to submit; backing
/// out with unscanned items confirms they'll be marked missing.
///
/// No backend/API changes — uses the existing scan + submit endpoints.
class InspectionScreen extends StatefulWidget {
  final Inspection inspection;

  const InspectionScreen({super.key, required this.inspection});

  @override
  State<InspectionScreen> createState() => _InspectionScreenState();
}

class _InspectionScreenState extends State<InspectionScreen> {
  final _service = InspectionService();
  final MobileScannerController _controller = MobileScannerController(
    formats: const [BarcodeFormat.qrCode],
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );

  late final int _inspectionId = widget.inspection.id;
  late final String _teacherName = widget.inspection.teacherName;
  late final int _total = widget.inspection.totalItems;
  late List<InspectionItem> _items;

  final Set<String> _verifiedCodes = {}; // QR strings already verified (dedupe)
  final Map<String, DateTime> _cooldown = {}; // per-code throttle
  int _verified = 0;
  bool _busy = false; // a scan API call is in flight
  bool _submitting = false;
  bool _completedShown = false;

  _ScanFeedback? _feedback;
  Timer? _feedbackTimer;
  int _feedbackSeq = 0;

  @override
  void initState() {
    super.initState();
    _items = List<InspectionItem>.from(widget.inspection.items);
    _verified = _items.where((i) => i.status == 'verified').length;
  }

  @override
  void dispose() {
    _feedbackTimer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  bool _inCooldown(String code) {
    final last = _cooldown[code];
    return last != null && DateTime.now().difference(last) < const Duration(milliseconds: 1500);
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy || _submitting || capture.barcodes.isEmpty) return;
    final code = capture.barcodes.first.rawValue;
    if (code == null || code.isEmpty || _inCooldown(code)) return;
    _cooldown[code] = DateTime.now();

    // Same QR already verified -> duplicate. Handle locally (no API call) so the
    // server's verified counter can't be double-incremented.
    if (_verifiedCodes.contains(code)) {
      _showFeedback('Already scanned', AppColors.misplaced, Icons.info_rounded);
      HapticFeedback.selectionClick();
      return;
    }

    setState(() => _busy = true);
    try {
      final result = await _service.scanEquipment(_inspectionId, code);
      final status = result['result'] as String? ?? 'invalid';
      final equipment = result['equipment'] as Map<String, dynamic>?;
      final name = (equipment?['name'] as String?) ?? 'item';

      switch (status) {
        case 'verified':
          _verifiedCodes.add(code);
          final id = equipment?['id'];
          final idx = _items.indexWhere((i) => i.equipmentId == id);
          if (idx >= 0) {
            final it = _items[idx];
            _items[idx] = InspectionItem(
              id: it.id, equipmentId: it.equipmentId, name: it.name, itemCode: it.itemCode, status: 'verified',
            );
          }
          _verified = _items.where((i) => i.status == 'verified').length;
          _showFeedback('Verified — $name', AppColors.verified, Icons.check_circle_rounded);
          HapticFeedback.mediumImpact();
          SystemSound.play(SystemSoundType.click);
          if (_verified >= _total && !_completedShown) {
            _completedShown = true;
            _showAllVerifiedDialog();
          }
          break;
        case 'misplaced':
          _showFeedback('Misplaced — $name', AppColors.missing, Icons.warning_rounded);
          HapticFeedback.heavyImpact();
          break;
        default: // invalid
          _showFeedback('Invalid QR code', AppColors.missing, Icons.close_rounded);
          HapticFeedback.heavyImpact();
      }
    } catch (e) {
      _showFeedback('Scan failed. Try again.', AppColors.missing, Icons.error_outline_rounded);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showFeedback(String message, Color color, IconData icon) {
    if (!mounted) return;
    setState(() => _feedback = _ScanFeedback(_feedbackSeq++, message, color, icon));
    _feedbackTimer?.cancel();
    _feedbackTimer = Timer(const Duration(milliseconds: 1100), () {
      if (mounted) setState(() => _feedback = null);
    });
  }

  Future<void> _showAllVerifiedDialog() async {
    await _controller.stop();
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: AppColors.verified.withValues(alpha: 0.12), shape: BoxShape.circle),
              child: Icon(Icons.verified_rounded, color: AppColors.verified, size: 48),
            ),
            const SizedBox(height: 16),
            Text('All items verified', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
            const SizedBox(height: 4),
            Text('$_verified/$_total items', style: TextStyle(color: AppColors.textSecondary)),
          ],
        ),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () { Navigator.of(context).pop(); _submit(); },
              icon: const Icon(Icons.check_rounded),
              label: const Text('Submit Inspection'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _handleFinishOrBack() async {
    if (_submitting) return;
    final pending = _items.where((i) => i.status != 'verified').toList();

    if (pending.isEmpty) {
      _submit();
      return;
    }

    await _controller.stop();
    if (!mounted) return;
    final names = pending.map((i) => i.name ?? i.itemCode ?? 'Unknown item').toList();
    final submit = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text('${pending.length} item${pending.length == 1 ? '' : 's'} not yet scanned'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('These will be marked as MISSING and reported to the admin:',
                style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
            const SizedBox(height: 12),
            ...names.take(8).map((n) => Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Row(children: [
                    Icon(Icons.circle, size: 6, color: AppColors.missing),
                    const SizedBox(width: 8),
                    Expanded(child: Text(n, style: TextStyle(color: AppColors.textPrimary))),
                  ]),
                )),
            if (names.length > 8)
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Text('and ${names.length - 8} more…', style: TextStyle(color: AppColors.textSecondary, fontSize: 13)),
              ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep Scanning')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AppColors.missing),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Submit with Missing'),
          ),
        ],
      ),
    );

    if (submit == true) {
      _submit();
    } else {
      await _controller.start();
    }
  }

  Future<void> _submit() async {
    if (_submitting) return;
    setState(() => _submitting = true);
    try {
      final result = await _service.submitInspection(_inspectionId);
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const MainScaffold()),
        (route) => false,
      );
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: AppColors.verified,
          content: Text('Inspection submitted — verified ${result.verifiedCount}/${result.totalItems}'
              '${result.missingCount > 0 ? ', ${result.missingCount} missing' : ''}.'),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.missing),
      );
      await _controller.start();
    }
  }

  Future<void> _showItemsSheet() async {
    await _controller.stop();
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      backgroundColor: AppColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      isScrollControlled: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.5,
        maxChildSize: 0.85,
        builder: (context, controller) => Column(
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Assigned Equipment', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                  Text('$_verified/$_total verified', style: TextStyle(color: AppColors.textSecondary)),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: ListView.builder(
                controller: controller,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                itemCount: _items.length,
                itemBuilder: (context, i) {
                  final item = _items[i];
                  final verified = item.status == 'verified';
                  return ListTile(
                    leading: Icon(
                      verified ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
                      color: verified ? AppColors.verified : AppColors.textSecondary,
                    ),
                    title: Text(item.name ?? 'Unknown item',
                        style: TextStyle(color: AppColors.textPrimary, fontWeight: verified ? FontWeight.w600 : FontWeight.w500)),
                    subtitle: Text(item.itemCode ?? '', style: TextStyle(color: AppColors.textSecondary, fontSize: 12)),
                    trailing: Text(verified ? 'Verified' : 'Pending',
                        style: TextStyle(color: verified ? AppColors.verified : AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w600)),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
    if (mounted && !_submitting) await _controller.start();
  }

  @override
  Widget build(BuildContext context) {
    final progress = _total == 0 ? 0.0 : (_verified / _total).clamp(0.0, 1.0);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _handleFinishOrBack();
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Stack(
          children: [
            // Camera
            Positioned.fill(
              child: MobileScanner(
                controller: _controller,
                onDetect: (capture) => _onDetect(capture),
                errorBuilder: (context, error, child) => Container(
                  color: Colors.black,
                  alignment: Alignment.center,
                  padding: const EdgeInsets.all(28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.no_photography_outlined, color: Colors.white70, size: 56),
                      const SizedBox(height: 14),
                      Text('The camera could not be started.\n${error.errorCode.name}',
                          textAlign: TextAlign.center, style: const TextStyle(color: Colors.white)),
                      const SizedBox(height: 20),
                      FilledButton.icon(
                        onPressed: () => _controller.start(),
                        icon: const Icon(Icons.refresh),
                        label: const Text('Try Again'),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Green scanning frame
            Center(
              child: Container(
                width: 250,
                height: 250,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.secondary, width: 3),
                  boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.4), blurRadius: 16)],
                ),
                child: Stack(children: const [
                  _Corner(alignment: Alignment.topLeft),
                  _Corner(alignment: Alignment.topRight),
                  _Corner(alignment: Alignment.bottomLeft),
                  _Corner(alignment: Alignment.bottomRight),
                ]),
              ),
            ),

            // Top counter bar
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _CircleButton(icon: Icons.arrow_back, onTap: _handleFinishOrBack),
                    const SizedBox(width: 10),
                    Expanded(child: _CounterPill(verified: _verified, total: _total, progress: progress, teacher: _teacherName)),
                    const SizedBox(width: 10),
                    ValueListenableBuilder(
                      valueListenable: _controller,
                      builder: (context, state, _) => _CircleButton(
                        icon: state.torchState == TorchState.on ? Icons.flash_on : Icons.flash_off,
                        onTap: () => _controller.toggleTorch(),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Non-blocking scan-result popup
            Positioned(
              left: 0, right: 0, bottom: 130,
              child: Center(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  transitionBuilder: (child, anim) => FadeTransition(
                    opacity: anim,
                    child: ScaleTransition(scale: Tween(begin: 0.9, end: 1.0).animate(anim), child: child),
                  ),
                  child: _feedback == null
                      ? const SizedBox.shrink(key: ValueKey('none'))
                      : _FeedbackPill(key: ValueKey(_feedback!.id), feedback: _feedback!),
                ),
              ),
            ),

            // Bottom actions
            Align(
              alignment: Alignment.bottomCenter,
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  child: Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _showItemsSheet,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            backgroundColor: Colors.black.withValues(alpha: 0.35),
                            side: const BorderSide(color: Colors.white54),
                            minimumSize: const Size.fromHeight(50),
                          ),
                          icon: const Icon(Icons.list_alt_rounded),
                          label: Text('Items ($_verified/$_total)'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _submitting ? null : _handleFinishOrBack,
                          icon: _submitting
                              ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Icon(Icons.flag_rounded),
                          label: const Text('Finish'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScanFeedback {
  final int id;
  final String message;
  final Color color;
  final IconData icon;
  _ScanFeedback(this.id, this.message, this.color, this.icon);
}

class _FeedbackPill extends StatelessWidget {
  final _ScanFeedback feedback;
  const _FeedbackPill({super.key, required this.feedback});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 24),
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.25), blurRadius: 16, offset: const Offset(0, 6))],
        border: Border(left: BorderSide(color: feedback.color, width: 5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(feedback.icon, color: feedback.color, size: 24),
          const SizedBox(width: 10),
          Flexible(
            child: Text(
              feedback.message,
              style: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w700, fontSize: 15),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _CounterPill extends StatelessWidget {
  final int verified;
  final int total;
  final double progress;
  final String teacher;
  const _CounterPill({required this.verified, required this.total, required this.progress, required this.teacher});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 12, offset: const Offset(0, 4))],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text('$verified/$total', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.primary)),
              const SizedBox(width: 4),
              Text('items', style: TextStyle(fontSize: 13, color: AppColors.textSecondary)),
              const Spacer(),
              Flexible(
                child: Text(teacher,
                    textAlign: TextAlign.right,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary, fontWeight: FontWeight.w500)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 6,
              backgroundColor: AppColors.border,
              valueColor: AlwaysStoppedAnimation(AppColors.secondary),
            ),
          ),
        ],
      ),
    );
  }
}

class _CircleButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _CircleButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black.withValues(alpha: 0.4),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(11),
          child: Icon(icon, color: Colors.white, size: 22),
        ),
      ),
    );
  }
}

class _Corner extends StatelessWidget {
  final Alignment alignment;
  const _Corner({required this.alignment});

  @override
  Widget build(BuildContext context) {
    final isTop = alignment.y < 0;
    final isLeft = alignment.x < 0;
    return Align(
      alignment: alignment,
      child: Container(
        width: 28,
        height: 28,
        margin: const EdgeInsets.all(6),
        decoration: BoxDecoration(
          border: Border(
            top: isTop ? const BorderSide(color: Colors.white, width: 4) : BorderSide.none,
            bottom: !isTop ? const BorderSide(color: Colors.white, width: 4) : BorderSide.none,
            left: isLeft ? const BorderSide(color: Colors.white, width: 4) : BorderSide.none,
            right: !isLeft ? const BorderSide(color: Colors.white, width: 4) : BorderSide.none,
          ),
        ),
      ),
    );
  }
}
