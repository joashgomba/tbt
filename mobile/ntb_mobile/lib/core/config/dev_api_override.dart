import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../l10n/locale_controller.dart' show sharedPreferencesProvider;
import 'app_config.dart';

const _storageKey = 'dev_api_base_url';

/// Compile-time config from `--dart-define` (set in `main.dart`).
final compileTimeAppConfigProvider = Provider<AppConfig>((ref) {
  throw UnimplementedError('compileTimeAppConfigProvider must be overridden in main()');
});

class DevApiOverrideNotifier extends Notifier<String?> {
  @override
  String? build() {
    final prefs = ref.watch(sharedPreferencesProvider);
    return prefs.getString(_storageKey);
  }

  Future<void> setOverride(String? apiBaseUrl) async {
    final prefs = ref.read(sharedPreferencesProvider);
    if (apiBaseUrl == null || apiBaseUrl.trim().isEmpty) {
      await prefs.remove(_storageKey);
      state = null;
      return;
    }
    final normalized = _normalizeApiBase(apiBaseUrl.trim());
    await prefs.setString(_storageKey, normalized);
    state = normalized;
  }

  static String _normalizeApiBase(String input) {
    var url = input;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'http://$url';
    }
    url = url.replaceAll(RegExp(r'/+$'), '');
    if (!url.endsWith('/api/v1')) {
      if (url.contains(':8000')) {
        url = '$url/api/v1';
      } else {
        url = '$url:8000/api/v1';
      }
    }
    return url;
  }
}

final devApiBaseOverrideProvider =
    NotifierProvider<DevApiOverrideNotifier, String?>(DevApiOverrideNotifier.new);

/// Active config: compile-time base + optional dev override (non-production only).
final appConfigProvider = Provider<AppConfig>((ref) {
  final base = ref.watch(compileTimeAppConfigProvider);
  if (base.isProduction) return base;
  final override = ref.watch(devApiBaseOverrideProvider);
  if (override == null || override.isEmpty) return base;
  return AppConfig(
    environment: base.environment,
    apiBaseUrl: override,
    appName: base.appName,
  );
});

/// Presets for physical-device local API (non-production).
abstract final class DevApiPresets {
  static const usbLocalhost = 'http://127.0.0.1:8000/api/v1';

  static String wifiHost(String host) {
    final h = host.replaceAll(RegExp(r'^https?://'), '').split('/').first.split(':').first;
    return 'http://$h:8000/api/v1';
  }
}
