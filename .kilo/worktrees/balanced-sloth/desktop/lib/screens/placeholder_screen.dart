import 'package:flutter/material.dart';

/// Temporary content for admin sections not yet ported to the desktop app.
/// Each of these will be replaced by a real list/detail screen backed by the
/// matching /api/admin/... endpoint.
class PlaceholderScreen extends StatelessWidget {
  final String title;
  const PlaceholderScreen({super.key, required this.title});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.construction_rounded, size: 56, color: Colors.grey.shade400),
          const SizedBox(height: 16),
          Text(title, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Text('This section is not built yet.',
              style: TextStyle(color: Colors.grey.shade600)),
        ],
      ),
    );
  }
}
