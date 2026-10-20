import 'package:dio/dio.dart';

/// A failed API call with the server's message, error code and field errors.
class ApiException implements Exception {
  ApiException(this.status, this.message, {this.code, this.errors = const {}});

  /// 0 when the server could not be reached.
  final int status;
  final String message;

  /// LessonLoop flow codes: no_students_left, answer_pending, …
  final String? code;
  final Map<String, List<String>> errors;

  bool get isOffline => status == 0;

  String? fieldError(String field) => errors[field]?.first;

  /// The first validation message for 422, otherwise the server message.
  String get userMessage => status == 422 && errors.isNotEmpty ? errors.values.first.first : message;

  @override
  String toString() => 'ApiException($status, $message)';
}

/// Thin wrapper over Dio: JSON in and out, errors as [ApiException].
class ApiClient {
  ApiClient({required String baseUrl, String? token, this.onUnauthorized, HttpClientAdapter? adapter})
    : _dio = Dio(
        BaseOptions(
          baseUrl: baseUrl,
          connectTimeout: const Duration(seconds: 8),
          receiveTimeout: const Duration(seconds: 20),
          headers: {'Accept': 'application/json', if (token != null) 'Authorization': 'Bearer $token'},
        ),
      ) {
    if (adapter != null) {
      _dio.httpClientAdapter = adapter;
    }
  }

  final Dio _dio;
  final void Function()? onUnauthorized;

  String get baseUrl => _dio.options.baseUrl;

  Future<Map<String, dynamic>> get(String path, [Map<String, dynamic>? query]) => _send('GET', path, query: query);

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) => _send('POST', path, body: body ?? const {});

  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) => _send('PATCH', path, body: body);

  Future<Map<String, dynamic>> delete(String path) => _send('DELETE', path);

  Future<Map<String, dynamic>> _send(String method, String path, {Map<String, dynamic>? query, Object? body}) async {
    try {
      final response = await _dio.request<dynamic>(
        path,
        data: body,
        queryParameters: query == null ? null : (Map.of(query)..removeWhere((_, value) => value == null || value == '')),
        options: Options(method: method),
      );
      final data = response.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{};
    } on DioException catch (error) {
      throw _toApiException(error);
    }
  }

  ApiException _toApiException(DioException error) {
    final response = error.response;
    if (response == null) {
      return ApiException(0, "Serverga ulanib bo'lmadi. Internet yoki server manzilini tekshiring.");
    }

    final status = response.statusCode ?? 0;
    final data = response.data is Map<String, dynamic> ? response.data as Map<String, dynamic> : const <String, dynamic>{};
    if (status == 401 && _dio.options.headers.containsKey('Authorization')) {
      onUnauthorized?.call();
    }

    final errors = <String, List<String>>{};
    final raw = data['errors'];
    if (raw is Map) {
      raw.forEach((key, value) => errors['$key'] = value is List ? value.map((item) => '$item').toList() : ['$value']);
    }

    return ApiException(
      status,
      data['message'] as String? ?? (status >= 500 ? 'Serverda xatolik ($status)' : 'Xatolik ($status)'),
      code: data['code'] as String?,
      errors: errors,
    );
  }
}

/// A readable message for any error thrown while talking to the API.
String errorText(Object error) => error is ApiException ? error.userMessage : "Kutilmagan xatolik: $error";
