import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/firebase_options.dart';
import 'push_api.dart';

/// Background handler must be top-level.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
}

final pushServiceProvider = Provider<PushService>((ref) => PushService(ref));

class PushService {
  PushService(this._ref);

  final Ref _ref;
  static const _fcmEnabled = bool.fromEnvironment('FCM_ENABLED', defaultValue: false);

  final _localNotifications = FlutterLocalNotificationsPlugin();
  String? _currentToken;

  String? get currentToken => _currentToken;

  Future<void> initialize() async {
    if (!_fcmEnabled || kIsWeb) return;

    try {
      await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    } catch (e) {
      debugPrint('Firebase init skipped: $e');
      return;
    }

    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings();
    await _localNotifications.initialize(
      const InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: (_) {},
    );

    await FirebaseMessaging.instance.requestPermission();
    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(
          const AndroidNotificationChannel(
            'ntb_case_updates',
            'Case updates',
            description: 'Notifications when your NTB case status changes',
            importance: Importance.high,
          ),
        );

    FirebaseMessaging.onMessage.listen(_showForegroundNotification);

    _currentToken = await FirebaseMessaging.instance.getToken();
    if (_currentToken != null) {
      await _registerWithBackend(_currentToken!);
    }

    FirebaseMessaging.instance.onTokenRefresh.listen((token) async {
      _currentToken = token;
      await _registerWithBackend(token);
    });
  }

  Future<void> _registerWithBackend(String token) async {
    try {
      final platform = Platform.isIOS ? 'ios' : 'android';
      await _ref.read(pushApiProvider).registerToken(token, platform: platform);
    } catch (e) {
      debugPrint('Push register failed (login required?): $e');
    }
  }

  Future<void> syncTokenAfterLogin() async {
    if (_currentToken != null) {
      await _registerWithBackend(_currentToken!);
    } else if (_fcmEnabled) {
      await initialize();
    }
  }

  Future<void> setCaseUpdatesEnabled(bool enabled) async {
    if (_currentToken == null) return;
    await _ref.read(pushApiProvider).setNotifyCaseUpdates(_currentToken!, enabled);
  }

  void _showForegroundNotification(RemoteMessage message) {
    final notification = message.notification;
    if (notification == null) return;

    _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'ntb_case_updates',
          'Case updates',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(),
      ),
    );
  }
}
