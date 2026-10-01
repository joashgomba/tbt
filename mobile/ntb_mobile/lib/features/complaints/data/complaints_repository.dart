import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/config/providers.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import '../domain/complaint_models.dart';

final complaintsRepositoryProvider = Provider<ComplaintsRepository>((ref) {
  return ComplaintsRepository(
    dio: ref.watch(dioProvider),
    config: ref.watch(appConfigProvider),
  );
});

class ComplaintsRepository {
  ComplaintsRepository({required Dio dio, required AppConfig config})
      : _dio = dio,
        _config = config;

  final Dio _dio;
  final AppConfig _config;

  Future<LookupsBundle> fetchLookups() async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '${_config.mobileApiBase}/lookups',
    );
    return LookupsBundle.fromJson(response.data!);
  }

  Future<List<ComplaintSummary>> listMine({int limit = 20}) async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '${_config.mobileApiBase}/complaints',
      query: {'limit': limit},
    );
    final rows = response.data!['data'] as List<dynamic>? ?? [];
    return rows.map((e) => ComplaintSummary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ComplaintDetail> getMine(String code) async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '${_config.mobileApiBase}/complaints/$code',
    );
    return ComplaintDetail.fromJson(response.data!['complaint'] as Map<String, dynamic>);
  }

  Future<ComplaintDetail> trackPublic(String code) async {
    final response = await apiGet<Map<String, dynamic>>(
      _dio,
      '${_config.publicApiBase}/complaints/$code',
    );
    return ComplaintDetail.fromJson(response.data!['complaint'] as Map<String, dynamic>);
  }

  Future<ComplaintDetail> submit({
    required Map<String, dynamic> payload,
    List<PlatformFile> files = const [],
    bool confirmDuplicate = false,
  }) async {
    if (files.isEmpty) {
      final body = {...payload, if (confirmDuplicate) 'confirm_duplicate': true};
      try {
        final response = await apiPost<Map<String, dynamic>>(
          _dio,
          '${_config.mobileApiBase}/complaints',
          data: body,
        );
        return ComplaintDetail.fromJson(response.data!['complaint'] as Map<String, dynamic>);
      } on ApiException catch (e) {
        if (e.isDuplicateWarning) rethrow;
        rethrow;
      }
    }

    final form = FormData.fromMap({
      ...payload.map((k, v) => MapEntry(k, v?.toString())),
      if (confirmDuplicate) 'confirm_duplicate': '1',
    });

    for (var i = 0; i < files.length; i++) {
      final file = files[i];
      if (file.path == null) continue;
      form.files.add(
        MapEntry(
          'attachments[$i]',
          await MultipartFile.fromFile(file.path!, filename: file.name),
        ),
      );
    }

    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '${_config.mobileApiBase}/complaints',
        data: form,
        options: Options(contentType: 'multipart/form-data'),
      );
      return ComplaintDetail.fromJson(response.data!['complaint'] as Map<String, dynamic>);
    } on DioException catch (e) {
      throw _mapMultipartError(e);
    }
  }

  ApiException _mapMultipartError(DioException e) {
    final response = e.response;
    final status = response?.statusCode;
    final data = response?.data;
    var message = e.message ?? 'Upload failed';
    if (data is Map && data['message'] is String) {
      message = data['message'] as String;
    }
    return ApiException(message, statusCode: status);
  }
}
