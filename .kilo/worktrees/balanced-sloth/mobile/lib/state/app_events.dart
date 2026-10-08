import 'package:flutter/foundation.dart';

/// Set to true in main() once Firebase.initializeApp() succeeds. When false
/// (e.g. placeholder google-services.json / no real Firebase project yet), the
/// push code no-ops so the rest of the app keeps working normally.
bool firebaseReady = false;

/// A tapped announcement push sets this to the announcement id. The Inbox
/// listens and opens that announcement's detail, then clears it back to null.
final ValueNotifier<int?> pendingAnnouncementId = ValueNotifier<int?>(null);

/// A tapped inspection push sets this to the inspection id. The app shell
/// listens and opens that inspection's detail, then clears it back to null.
final ValueNotifier<int?> pendingInspectionId = ValueNotifier<int?>(null);

/// Bumped whenever a push arrives/opens so the Inbox list + unread badge refresh.
final ValueNotifier<int> notificationsChanged = ValueNotifier<int>(0);
