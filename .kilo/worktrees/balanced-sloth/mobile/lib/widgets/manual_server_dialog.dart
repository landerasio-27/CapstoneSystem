import 'package:flutter/material.dart';

import '../services/api_client.dart';
import '../services/server_discovery.dart';
import '../theme/app_theme.dart';

/// Fallback for when automatic discovery can't find the server: lets a user
/// type the server address directly. Verifies the address against the real
/// backend before accepting it, exactly like discovery does.
///
/// Returns true when a valid server was saved.
Future<bool> showManualServerDialog(BuildContext context) async {
  final baseUrl = await ApiClient.getBaseUrl();
  if (!context.mounted) return false;
  final controller = TextEditingController(text: baseUrl);
  var saving = false;
  String? error;

  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: !saving,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) {
        Future<void> save() async {
          // Capture before any await so the dialog can be popped safely.
          final navigator = Navigator.of(context);
          setState(() {
            saving = true;
            error = null;
          });
          final url = controller.text.trim();
          if (url.isEmpty) {
            setState(() {
              saving = false;
              error = 'Enter the server address.';
            });
            return;
          }

          final server = await ServerDiscovery.verifyAt(url);
          if (server == null) {
            setState(() {
              saving = false;
              error =
                  'No ISU server answered at that address. Check the address and try again.';
            });
            return;
          }

          await ServerDiscovery.save(server);
          navigator.pop(true);
        }

        return AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
          title: const Row(
            children: [
              Icon(Icons.dns_outlined, color: AppColors.primary, size: 22),
              SizedBox(width: 10),
              Expanded(child: Text('Enter Server Manually')),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Only needed if automatic search can\u2019t find the server. '
                'The app verifies the address before saving.',
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: controller,
                enabled: !saving,
                keyboardType: TextInputType.url,
                decoration: const InputDecoration(
                  labelText: 'Server address',
                  hintText: 'http://192.168.1.10:8000/api',
                  prefixIcon: Icon(Icons.link_rounded),
                  errorMaxLines: 3,
                ),
              ),
              if (error != null) ...[
                const SizedBox(height: 8),
                Text(error!,
                    style: const TextStyle(color: AppColors.missing, fontSize: 12.5)),
              ],
            ],
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.of(context).pop(false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving ? null : save,
              child: saving
                  ? const SizedBox(
                      height: 18,
                      width: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Save & Connect'),
            ),
          ],
        );
      },
    ),
  );

  controller.dispose();
  return ok == true;
}