import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/config/providers.dart';
import '../../../core/network/api_client.dart';
import '../domain/transparency_models.dart';

final transparencyRepositoryProvider = Provider<TransparencyRepository>((ref) {
  return TransparencyRepository(
    dio: ref.watch(dioProvider),
    config: ref.watch(appConfigProvider),
  );
});

class TransparencyRepository {
  TransparencyRepository({required Dio dio, required AppConfig config})
      : _dio = dio,
        _config = config;

  final Dio _dio;
  final AppConfig _config;

  String get _base => _config.publicApiBase;

  Future<TransparencyLookups> lookups() async {
    final response = await apiGet<Map<String, dynamic>>(_dio, '$_base/transparency/lookups');
    return TransparencyLookups.fromJson(response.data!);
  }

  Future<TransparencyStats> stats({Map<String, dynamic>? filters}) async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '$_base/transparency/stats',
      query: filters,
    );
    return TransparencyStats.fromJson(response.data!);
  }

  Future<List<MapPoint>> mapPoints({Map<String, dynamic>? filters}) async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '$_base/transparency/map',
      query: filters,
    );
    final rows = response.data!['points'] as List<dynamic>? ?? [];
    return rows.map((e) => MapPoint.fromJson(e as Map<String, dynamic>)).toList();
  }
}
