import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/app_config.dart';
import '../config/providers.dart';
import '../storage/token_storage.dart';
import 'api_exception.dart';

final dioProvider = Provider<Dio>((ref) {
  final config = ref.watch(appConfigProvider);
  final storage = ref.watch(tokenStorageProvider);
  return createDio(config, storage);
});

Dio createDio(AppConfig config, TokenStorage storage) {
  final dio = Dio(
    BaseOptions(
      connectTimeout: const Duration(seconds: 25),
      receiveTimeout: const Duration(seconds: 45),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await storage.readToken();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) {
        handler.next(error);
      },
    ),
  );

  return dio;
}

Future<Response<T>> apiGet<T>(
  Dio dio,
  String url, {
  Map<String, dynamic>? query,
}) async {
  try {
    return await dio.get<T>(url, queryParameters: query);
  } on DioException catch (e) {
    throw _mapError(e);
  }
}

Future<Response<T>> apiPost<T>(
  Dio dio,
  String url, {
  Object? data,
  Options? options,
}) async {
  try {
    return await dio.post<T>(url, data: data, options: options);
  } on DioException catch (e) {
    throw _mapError(e);
  }
}

ApiException _mapError(DioException e) {
  final response = e.response;
  final status = response?.statusCode;
  final data = response?.data;
  String message = e.message ?? 'Network error';

  if (e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      e.type == DioExceptionType.receiveTimeout) {
    final host = e.requestOptions.uri.host;
    message = 'Timed out reaching $host. Check API URL and network.';
    return ApiException(message, statusCode: status, kind: ApiErrorKind.timeout);
  }
  if (e.type == DioExceptionType.connectionError) {
    final host = e.requestOptions.uri.host;
    message = 'Cannot connect to $host. Check Wi‑Fi and API URL.';
    return ApiException(message, statusCode: status, kind: ApiErrorKind.connection);
  }

  if (data is Map) {
    if (data['message'] is String) {
      message = data['message'] as String;
    } else if (data['errors'] is Map) {
      final errors = data['errors'] as Map;
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) {
        message = first.first.toString();
      }
    }
  }

  Map<String, dynamic>? validation;
  if (data is Map && data['errors'] is Map) {
    validation = Map<String, dynamic>.from(data['errors'] as Map);
  }

  return ApiException(message, statusCode: status, validationErrors: validation);
}
