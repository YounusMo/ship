import 'package:dio/dio.dart';
import 'api_exceptions.dart';

/// One Dio instance, configured with the configured base URL and the
/// current bearer token. The token is supplied by an external lambda so
/// the client can be rebuilt cheaply when auth state changes.
///
/// On a 401 the interceptor calls `POST /api/v1/employee/auth/refresh`
/// exactly once. On success the new token is fed back via
/// `onTokenRefreshed` (auth_state persists it) and the original request
/// is retried transparently. On failure `onAuthFailed` is called so the
/// router redirects the user to /login. See gap #4 in docs/GAPS.md.
class ApiClient {
  ApiClient({
    required this.baseUrl,
    required this.tokenProvider,
    this.onTokenRefreshed,
    this.onAuthFailed,
  }) : dio = _build(baseUrl, tokenProvider) {
    _attachRefreshInterceptor();
  }

  final String baseUrl;
  final String? Function() tokenProvider;
  final Future<void> Function(String newToken)? onTokenRefreshed;
  final Future<void> Function()? onAuthFailed;
  final Dio dio;

  Future<bool>? _refreshInFlight;

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

  void _attachRefreshInterceptor() {
    dio.interceptors.add(InterceptorsWrapper(
      onResponse: (response, handler) async {
        if (response.statusCode != 401) return handler.next(response);

        final opts = response.requestOptions;
        final path = opts.path;
        // Don't refresh on the refresh/login endpoints themselves.
        if (path.contains('/auth/refresh') || path.contains('/auth/login')) {
          return handler.next(response);
        }
        if (tokenProvider() == null || onTokenRefreshed == null) {
          return handler.next(response);
        }

        final ok = await _refreshOnce();
        if (!ok) {
          await onAuthFailed?.call();
          return handler.next(response);
        }

        try {
          final t = tokenProvider();
          if (t != null) opts.headers['Authorization'] = 'Bearer $t';
          final retry = await dio.fetch<dynamic>(opts);
          return handler.resolve(retry);
        } catch (_) {
          return handler.next(response);
        }
      },
    ));
  }

  Future<bool> _refreshOnce() {
    return _refreshInFlight ??= _doRefresh().whenComplete(() => _refreshInFlight = null);
  }

  Future<bool> _doRefresh() async {
    final t = tokenProvider();
    if (t == null) return false;
    try {
      final bare = Dio(BaseOptions(
        baseUrl       : baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers       : <String, dynamic>{
          'Accept'       : 'application/json',
          'Authorization': 'Bearer $t',
        },
        validateStatus: (_) => true,
      ));
      final r = await bare.post<dynamic>('/api/v1/employee/auth/refresh');
      if (r.statusCode == 200 && r.data is Map && (r.data as Map)['token'] is String) {
        final newToken = (r.data as Map)['token'] as String;
        await onTokenRefreshed?.call(newToken);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
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
