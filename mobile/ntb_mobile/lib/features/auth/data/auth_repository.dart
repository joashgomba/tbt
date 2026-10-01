import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/config/providers.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import '../../../core/utils/device_name.dart';
import '../domain/auth_user.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    dio: ref.watch(dioProvider),
    config: ref.watch(appConfigProvider),
    storage: ref.watch(tokenStorageProvider),
  );
});

class AuthRepository {
  AuthRepository({
    required Dio dio,
    required this.config,
    required TokenStorage storage,
  })  : _dio = dio,
        _storage = storage;

  final Dio _dio;
  final AppConfig config;
  final TokenStorage _storage;

  String get _base => config.mobileApiBase;

  Future<AuthSession> login({required String email, required String password}) async {
    final deviceName = await resolveDeviceName();
    final response = await apiPost<Map<String, dynamic>>(
      _dio,
      '$_base/auth/login',
      data: {
        'email': email,
        'password': password,
        'device_name': deviceName,
      },
    );
    final data = response.data!;
    final token = data['access_token'] as String;
    await _storage.saveToken(token);
    return AuthSession(token: token, user: AuthUser.fromJson(data['user'] as Map<String, dynamic>));
  }

  Future<AuthSession> register({
    required String fullName,
    required String email,
    required String password,
    required String passwordConfirmation,
    required String phoneNumber,
    required String organizationName,
    required String country,
    required bool acceptTerms,
    String? preferredLanguage,
  }) async {
    final deviceName = await resolveDeviceName();
    final response = await apiPost<Map<String, dynamic>>(
      _dio,
      '$_base/auth/register',
      data: {
        'full_name': fullName,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'phone_number': phoneNumber,
        'organization_name': organizationName,
        'country': country,
        'preferred_language': preferredLanguage ?? 'en',
        'device_name': deviceName,
        'accept_terms': acceptTerms,
      },
    );
    final data = response.data!;
    final token = data['access_token'] as String;
    await _storage.saveToken(token);
    return AuthSession(token: token, user: AuthUser.fromJson(data['user'] as Map<String, dynamic>));
  }

  Future<AuthUser?> restoreSession() async {
    final token = await _storage.readToken();
    if (token == null || token.isEmpty) return null;
    try {
      final response = await apiGet<Map<String, dynamic>>(
        _dio,
        '$_base/auth/me',
      );
      return AuthUser.fromJson(response.data!['user'] as Map<String, dynamic>);
    } catch (_) {
      await _storage.clearToken();
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await apiPost<void>(_dio, '$_base/auth/logout');
    } catch (_) {
      // ignore network errors on logout
    }
    await _storage.clearToken();
  }
}
