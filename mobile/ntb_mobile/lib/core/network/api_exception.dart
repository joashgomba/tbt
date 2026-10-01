enum ApiErrorKind { generic, timeout, connection }

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.validationErrors, this.kind = ApiErrorKind.generic});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? validationErrors;
  final ApiErrorKind kind;

  bool get isDuplicateWarning => statusCode == 409;

  @override
  String toString() => message;
}
