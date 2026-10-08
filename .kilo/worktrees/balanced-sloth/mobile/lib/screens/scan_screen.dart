import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../theme/app_theme.dart';

/// Generic QR scanner screen with a green framed overlay. Pops the scanned
/// code's raw string value once a barcode is detected. Used for both teacher
/// QR codes and equipment QR codes.
class ScanScreen extends StatefulWidget {
  final String title;
  final String instructions;

  const ScanScreen({super.key, required this.title, required this.instructions});

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  // Restrict to QR codes on the back camera — less work for the barcode engine
  // and avoids format/lens edge cases on some OEM camera stacks.
  final MobileScannerController _controller = MobileScannerController(
    formats: const [BarcodeFormat.qrCode],
    detectionSpeed: DetectionSpeed.noDuplicates,
    facing: CameraFacing.back,
  );
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    if (capture.barcodes.isEmpty) return;
    final value = capture.barcodes.first.rawValue;
    if (value == null || value.isEmpty) return;

    _handled = true;
    Navigator.of(context).pop(value);
  }

  String _friendlyError(MobileScannerException error) {
    switch (error.errorCode) {
      case MobileScannerErrorCode.permissionDenied:
        return 'Camera permission is required to scan QR codes. '
            'Please enable it in Settings › Apps › ISU Supply Inspection › Permissions.';
      case MobileScannerErrorCode.unsupported:
        return 'QR scanning is not supported on this device.';
      default:
        return 'The camera could not be started. Please try again.\n\n'
            '(${error.errorDetails?.message ?? error.errorCode.name})';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        elevation: 0,
        title: Text(widget.title, style: const TextStyle(color: Colors.white)),
        actions: [
          IconButton(
            icon: ValueListenableBuilder(
              valueListenable: _controller,
              builder: (context, state, child) => Icon(
                state.torchState == TorchState.on ? Icons.flash_on : Icons.flash_off,
                color: Colors.white,
              ),
            ),
            onPressed: () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error, child) => _ErrorView(
              message: _friendlyError(error),
              onRetry: () => _controller.start(),
              onCancel: () => Navigator.of(context).pop(),
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
                boxShadow: [
                  BoxShadow(color: AppColors.primary.withValues(alpha: 0.4), blurRadius: 16),
                ],
              ),
              child: Stack(
                children: const [
                  _Corner(alignment: Alignment.topLeft),
                  _Corner(alignment: Alignment.topRight),
                  _Corner(alignment: Alignment.bottomLeft),
                  _Corner(alignment: Alignment.bottomRight),
                ],
              ),
            ),
          ),

          // Instruction banner
          Align(
            alignment: Alignment.bottomCenter,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 40),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                  colors: [Colors.black.withValues(alpha: 0.8), Colors.transparent],
                ),
              ),
              child: Row(
                children: [
                  Icon(Icons.qr_code_scanner, color: AppColors.secondary),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(widget.instructions, style: const TextStyle(color: Colors.white, fontSize: 14)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  final VoidCallback onCancel;

  const _ErrorView({required this.message, required this.onRetry, required this.onCancel});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.no_photography_outlined, color: Colors.white70, size: 56),
          const SizedBox(height: 16),
          Text(message, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 14)),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Try Again'),
            style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: onCancel,
            child: const Text('Cancel', style: TextStyle(color: Colors.white70)),
          ),
        ],
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
