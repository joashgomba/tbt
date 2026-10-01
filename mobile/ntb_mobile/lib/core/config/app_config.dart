import 'dart:io';

import 'package:flutter/foundation.dart';

/// Single configuration entry point for local, staging, and production backends.
///
/// Override at build/run time:
/// ```bash
/// flutter run --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://portal.example.com/api/v1
/// ```
enum AppEnvironment { local, staging, production }

@immutable
class AppConfig {
  const AppConfig({
    required this.environment,
    required this.apiBaseUrl,
    required this.appName,
  });

  final AppEnvironment environment;
  final String apiBaseUrl;
  final String appName;

  String get mobileApiBase => '$apiBaseUrl/mobile';
  String get publicApiBase => '$apiBaseUrl/public';

  bool get isProduction => environment == AppEnvironment.production;

  static AppConfig load() {
    const envName = String.fromEnvironment('APP_ENV', defaultValue: 'local');
    const overrideBase = String.fromEnvironment('API_BASE_URL', defaultValue: '');
    const localHost = String.fromEnvironment('LOCAL_API_HOST', defaultValue: '');
    const prodDefault = String.fromEnvironment(
      'PRODUCTION_API_BASE',
      defaultValue: 'https://ntb.dj/api/v1',
    );
    const stagingDefault = String.fromEnvironment(
      'STAGING_API_BASE',
      defaultValue: 'https://staging.ntb.dj/api/v1',
    );

    final environment = AppEnvironment.values.firstWhere(
      (e) => e.name == envName,
      orElse: () => AppEnvironment.local,
    );

    if (overrideBase.isNotEmpty) {
      return AppConfig(
        environment: environment,
        apiBaseUrl: _trimTrailingSlash(overrideBase),
        appName: _appTitle(environment),
      );
    }

    if (environment == AppEnvironment.local && localHost.isNotEmpty) {
      final host = localHost.replaceAll(RegExp(r'^https?://'), '').split('/').first;
      return AppConfig(
        environment: environment,
        apiBaseUrl: 'http://$host:8000/api/v1',
        appName: _appTitle(environment),
      );
    }

    final base = switch (environment) {
      AppEnvironment.production => prodDefault,
      AppEnvironment.staging => stagingDefault,
      AppEnvironment.local => _localApiBase(),
    };

    return AppConfig(
      environment: environment,
      apiBaseUrl: _trimTrailingSlash(base),
      appName: _appTitle(environment),
    );
  }

  static String _localApiBase() {
    if (kIsWeb) return 'http://127.0.0.1:8000/api/v1';
    if (Platform.isAndroid) return 'http://10.0.2.2:8000/api/v1';
    return 'http://127.0.0.1:8000/api/v1';
  }

  static String _trimTrailingSlash(String url) {
    return url.endsWith('/') ? url.substring(0, url.length - 1) : url;
  }

  static String _appTitle(AppEnvironment env) {
    return switch (env) {
      AppEnvironment.production => 'NTB Djibouti',
      AppEnvironment.staging => 'NTB Djibouti (Staging)',
      AppEnvironment.local => 'NTB Djibouti (Local)',
    };
  }
}
