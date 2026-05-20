import 'dart:io' show Platform;

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../api/api_service.dart';

/// Owns the FCM lifecycle:
///   - bootstrap() — request permission, wire foreground/background handlers.
///   - registerWithBackend() — fetches the device token and POSTs it to the
///     API so server-side fan-outs can reach this device. Called after every
///     successful login.
///
/// Token rotation: FCM rotates tokens silently. We re-register on every cold
/// start (auth state restore) so we never sit on a stale token.
class PushService {
  PushService._();
  static final PushService instance = PushService._();

  final _localNotifications = FlutterLocalNotificationsPlugin();
  bool _bootstrapped = false;

  Future<void> bootstrap() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    final settings = await FirebaseMessaging.instance.requestPermission(
      alert: true, badge: true, sound: true,
    );
    debugPrint('[push] permission: ${settings.authorizationStatus}');

    // Foreground messages don't display by default — show a local notification
    // so users see the alert even when the app is in the foreground.
    const initSettings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(),
    );
    await _localNotifications.initialize(initSettings);

    FirebaseMessaging.onMessage.listen((message) {
      final title = message.notification?.title ?? 'Update';
      final body  = message.notification?.body  ?? '';
      _localNotifications.show(
        message.hashCode,
        title,
        body,
        const NotificationDetails(
          android: AndroidNotificationDetails(
            'shipflow_default',
            'ShipFlow notifications',
            channelDescription: 'Transactions, shipments, receipts',
            importance: Importance.high,
            priority: Priority.high,
          ),
          iOS: DarwinNotificationDetails(presentAlert: true, presentBadge: true, presentSound: true),
        ),
      );
    });

    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      // Deep-link target lives in message.data['category'] — leave routing
      // to a follow-up commit; for now we just log so the wiring is visible.
      debugPrint('[push] opened from notification: ${message.data}');
    });
  }

  /// Pulls the FCM token and POSTs it to /api/devices/register. Safe to
  /// call multiple times (the backend upserts on token).
  Future<void> registerWithBackend() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null) {
        debugPrint('[push] FCM token is null — permission denied or no Firebase config?');
        return;
      }
      final platform = Platform.isIOS ? 'ios' : 'android';
      await apiService.registerDevice(
        platform: platform,
        token: token,
      );
      debugPrint('[push] registered ${token.substring(0, 12)}… with backend');
    } catch (e) {
      debugPrint('[push] registerWithBackend failed: $e');
    }
  }
}
