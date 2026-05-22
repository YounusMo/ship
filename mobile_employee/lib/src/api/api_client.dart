import 'package:dio/dio.dart';
import 'api_exceptions.dart';

/// One Dio instance, configured with the configured base URL and the
/// current bearer token. The token is supplied by an external lambda so
/// the client can be rebuilt cheaply when auth state changes.
class ApiClient {
  ApiClient({
    required String baseUrl,
    required String? Function() tokenProvider,
  }) : dio = _build(baseUrl, tokenProvider);

  final Dio dio;

  static Dio _build(String baseUrl, String? Function() tokenProvider) {
    final dio = Dio(BaseOptions(
      baseUrl       : baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 30),
      headers       : <String, dynamic>{'Accept': 'application/json'},
      // Don't let Dio throw on non-2xx — we map status codes ourselves.
      validateStatus: (_) => true,
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final t = tokenProvider();
        if (t != null && t.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $t';
        }
        handler.next(options);
      },
    ));

    return dio;
  }

  /// Maps a Dio response into a typed exception, OR returns the data
  /// when the status is 2xx. Caller passes the response shape it expects.
  static Map<String, dynamic> jsonOr(ApiException Function(int, Map<String, dynamic>?) onError, Response<dynamic> resp) {
    final status = resp.statusCode ?? 0;
    final body = resp.data;
    final map = body is Map<String, dynamic> ? body : <String, dynamic>{};

    if (status >= 200 && status < 300) {
      return map;
    }
    throw onError(status, map);
  }

  /// Default error mapper — used by the typed service when the call site
  /// doesn't need finer control. Translates HTTP status + `type` field into
  /// one of the ApiException subclasses.
  static ApiException defaultErrorMapper(int status, Map<String, dynamic>? body) {
    final wireType = body?['type'] as String?;
    final message  = (body?['message'] as String?) ?? 'HTTP $status';

    return switch (status) {
      401 => ApiAuthException(message),
      403 => ApiForbiddenException(message, wireType: wireType),
      404 => ApiNotFoundException(message),
      409 => ApiConflictException(message, wireType: wireType),
      422 => ApiValidationException(message, wireType: wireType),
      429 => ApiRateLimitedException(
        message,
        retryAfterSeconds: (body?['retry_after_s'] as num?)?.toInt() ?? 60,
      ),
      _ when status >= 500 => ApiServerException(message, status: status),
      _ => ApiServerException(message, status: status),
    };
  }
}
