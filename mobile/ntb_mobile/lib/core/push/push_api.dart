import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../config/providers.dart';
import '../network/api_client.dart';
import '../utils/device_name.dart';

final pushApiProvider = Provider<PushApi>((ref) {
  return PushApi(dio: ref.watch(dioProvider), config: ref.watch(appConfigProvider));
});

class PushApi {
  PushApi({required Dio dio, required AppConfig config}) : _dio = dio, _config = config;

  final Dio _dio;
  final AppConfig _config;

  Future<void> registerToken(String fcmToken, {String platform = 'unknown'}) async {
    final deviceName = await resolveDeviceName();
    await apiPost(
      _dio,
      '${_config.mobileApiBase}/push/register',
      data: {
        'fcm_token': fcmToken,
        'platform': platform,
        'device_name': deviceName,
      },
    );
  }

  Future<void> unregisterToken(String fcmToken) async {
    await apiPost(
      _dio,
      '${_config.mobileApiBase}/push/unregister',
      data: {'fcm_token': fcmToken},
    );
  }

  Future<void> setNotifyCaseUpdates(String fcmToken, bool enabled) async {
    await _dio.put(
      '${_config.mobileApiBase}/push/preferences',
      data: {
        'fcm_token': fcmToken,
        'notify_case_updates': enabled,
      },
    );
  }
}
