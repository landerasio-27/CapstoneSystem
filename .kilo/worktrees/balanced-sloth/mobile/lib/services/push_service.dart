import 'dart:typed_data';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../state/app_events.dart';
import 'inspection_service.dart';

/// Background/terminated message handler. For notification messages FCM shows
/// the system-tray notification automatically (via the default channel declared
/// in AndroidManifest), so this only needs to exist and be registered.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // No-op: the tray notification is shown by the system. Deep-link routing on
  // tap is handled by getInitialMessage()/onMessageOpenedApp() in the app.
}

/// Handles FCM: permission, token registration + topic subscription, and the
/// three delivery states (foreground heads-up, background/terminated tray + tap
/// deep-linking). All calls fail soft so a missing/placeholder Firebase project
/// never breaks the rest of the app.
class PushService {
  PushService._();
  static final PushService instance = PushService._();

  static const _channelId = 'announcements';
  static const _channelInspections = 'inspections';
  static const _channelUrgent = 'urgent_inspections';
  static const _topic = 'announcements';

  final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();
  final InspectionService _api = InspectionService();

  bool _initialized = false;
  int _localId = 0;
  String? _lastToken;

  /// One-time setup: local-notification channel + FCM listeners. Safe to call
  /// repeatedly (guarded). Does nothing if Firebase isn't configured.
  Future<void> initialize() async {
    if (_initialized || !firebaseReady) return;
    _initialized = true;

    // Local notifications (used to show a heads-up banner while in foreground).
    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _local.initialize(
      const InitializationSettings(android: androidInit),
      onDidReceiveNotificationResponse: (response) => _routeFromPayload(response.payload),
    );

    final android = _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await android?.createNotificationChannel(const AndroidNotificationChannel(
      _channelId, 'Announcements',
      description: 'Announcements from the ISU Cauayan supply office',
      importance: Importance.high,
    ));
    await android?.createNotificationChannel(const AndroidNotificationChannel(
      _channelInspections, 'Inspections',
      description: 'New and scheduled inspection assignments',
      importance: Importance.high,
    ));
    // URGENT: max importance + a distinct, stronger triple-buzz vibration.
    await android?.createNotificationChannel(AndroidNotificationChannel(
      _channelUrgent, 'Urgent Inspections',
      description: 'Urgent inspection alerts',
      importance: Importance.max,
      enableVibration: true,
      vibrationPattern: Int64List.fromList(const [0, 500, 250, 500, 250, 500]),
    ));

    // Foreground messages -> show a local heads-up notification.
    FirebaseMessaging.onMessage.listen(_onForegroundMessage);

    // App opened from background by tapping a notification.
    FirebaseMessaging.onMessageOpenedApp.listen(_routeFromMessage);

    // App launched from terminated state by tapping a notification.
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _routeFromMessage(initial);
    }
  }

  /// Ask for the Android 13+ notification permission (call at first login).
  Future<void> requestPermission() async {
    if (!firebaseReady) return;
    try {
      await FirebaseMessaging.instance.requestPermission();
      await _local
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (_) {}
  }

  /// Get the device token, send it to the server, and subscribe to the topic.
  Future<void> registerToken() async {
    if (!firebaseReady) return;
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) {
        _lastToken = token;
        await _api.updateFcmToken(token);
        await FirebaseMessaging.instance.subscribeToTopic(_topic);
      }
    } catch (_) {
      // Placeholder/unconfigured Firebase — skip silently.
    }

    // Keep the server in sync if FCM rotates the token.
    FirebaseMessaging.instance.onTokenRefresh.listen((token) async {
      _lastToken = token;
      try {
        await _api.updateFcmToken(token);
        await FirebaseMessaging.instance.subscribeToTopic(_topic);
      } catch (_) {}
    });
  }

  /// On logout: unsubscribe, tell the server to forget the token, delete it.
  Future<void> unregister() async {
    if (!firebaseReady) return;
    try {
      final token = _lastToken ?? await FirebaseMessaging.instance.getToken();
      await FirebaseMessaging.instance.unsubscribeFromTopic(_topic);
      if (token != null) {
        await _api.removeFcmToken(token);
      }
      await FirebaseMessaging.instance.deleteToken();
      _lastToken = null;
    } catch (_) {}
  }

  void _onForegroundMessage(RemoteMessage message) {
    final data = message.data;
    final type = data['type'] ?? 'announcement';
    final urgent = data['urgent'] == '1';
    final notification = message.notification;
    final title = notification?.title ?? data['title'] ?? 'ISU Supply';
    final body = notification?.body ?? '';

    // Choose the matching channel so the heads-up (and urgent buzz) is consistent.
    final channelId = urgent
        ? _channelUrgent
        : (type == 'inspection' ? _channelInspections : _channelId);
    final channelName = urgent ? 'Urgent Inspections' : (type == 'inspection' ? 'Inspections' : 'Announcements');

    _local.show(
      _localId++,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          channelName,
          importance: urgent ? Importance.max : Importance.high,
          priority: urgent ? Priority.max : Priority.high,
          icon: '@mipmap/ic_launcher',
        ),
      ),
      // Encode the target so a tap on the local notification deep-links correctly.
      payload: '$type:${data['id'] ?? ''}',
    );

    notificationsChanged.value++;
  }

  void _routeFromMessage(RemoteMessage message) {
    _routeToTarget(message.data['type'] ?? 'announcement', message.data['id']?.toString());
    notificationsChanged.value++;
  }

  void _routeFromPayload(String? payload) {
    if (payload == null || payload.isEmpty) return;
    // Payload is "type:id"; older announcement payloads were just the id.
    final parts = payload.split(':');
    if (parts.length == 2) {
      _routeToTarget(parts[0], parts[1]);
    } else {
      _routeToTarget('announcement', payload);
    }
  }

  void _routeToTarget(String type, String? idStr) {
    final id = int.tryParse(idStr ?? '');
    if (id == null) return;
    if (type == 'inspection') {
      pendingInspectionId.value = id;
    } else {
      pendingAnnouncementId.value = id;
    }
  }
}
