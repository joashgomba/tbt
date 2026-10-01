import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/auth_repository.dart';
import '../domain/auth_user.dart';

final authControllerProvider =
    StateNotifierProvider<AuthController, AsyncValue<AuthUser?>>((ref) {
  return AuthController(ref.watch(authRepositoryProvider))..bootstrap();
});

class AuthController extends StateNotifier<AsyncValue<AuthUser?>> {
  AuthController(this._repo) : super(const AsyncValue.loading());

  final AuthRepository _repo;

  Future<void> bootstrap() async {
    try {
      final user = await _repo
          .restoreSession()
          .timeout(const Duration(seconds: 10), onTimeout: () => null);
      state = AsyncValue.data(user);
    } catch (_) {
      state = const AsyncValue.data(null);
    } finally {
      if (state.isLoading) {
        state = const AsyncValue.data(null);
      }
    }
  }

  Future<void> login(String email, String password) async {
    state = const AsyncValue.loading();
    try {
      final session = await _repo.login(email: email, password: password);
      state = AsyncValue.data(session.user);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
      rethrow;
    }
  }

  Future<void> register({
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
    state = const AsyncValue.loading();
    try {
      final session = await _repo.register(
        fullName: fullName,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        phoneNumber: phoneNumber,
        organizationName: organizationName,
        country: country,
        acceptTerms: acceptTerms,
        preferredLanguage: preferredLanguage,
      );
      state = AsyncValue.data(session.user);
    } catch (e, st) {
      state = AsyncValue.error(e, st);
      rethrow;
    }
  }

  Future<void> logout() async {
    await _repo.logout();
    state = const AsyncValue.data(null);
  }

  String? errorMessage(Object? error) {
    if (error is ApiException) return error.message;
    return error?.toString();
  }
}
