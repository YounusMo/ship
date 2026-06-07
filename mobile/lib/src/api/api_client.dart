import 'package:dio/dio.dart';
import 'package:dio_cache_interceptor/dio_cache_interceptor.dart';
import 'package:flutter/foundation.dart';

import 'api_exceptions.dart';

/// Single-source-of-truth [Dio] instance. Two interceptors:
///
/// 1. Token: attaches `Authorization: Bearer <token>` to every authenticated
///    request. Token is provided by the auth state, which calls
///    [setToken] / [clearToken] on login / logout.
///
/// 2. Error normalizer: maps Dio's error types onto our small
///    [ApiException] enum so screens don't need to know about Dio.
class ApiClient {
  ApiClient._() {
    _dio = Dio(BaseOptions(
      baseUrl: _baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout:    const Duration(seconds: 20),
      headers: <String, String>{
        'Accept'      : 'application/json',
        'Content-Type': 'application/json',
      },
      // Status validation lives in the interceptor; let Dio surface every
      // response so we can map error codes uniformly.
      validateStatus: (_) => true,
    ));

    // In-memory cache for GET responses. dio_cache_interceptor 3.5+ no
    // longer ships a file-backed store in the base package; switching to
    // dio_cache_interceptor_db_store would pull in sqflite which crashed
    // on cold start during prior testing. Memory-only is acceptable:
    // dashboard reads re-fetch on launch, which costs one round-trip but
    // avoids the stale-on-disk hazard entirely. clearToken() flushes the
    // whole store on logout so the next user on this device can't read
    // the previous client's data.
    _cacheOptions = CacheOptions(
      store: MemCacheStore(maxSize: 5 * 1024 * 1024),
      policy: CachePolicy.request,
      hitCacheOnErrorExcept: const <int>[401, 403],
      maxStale: const Duration(minutes: 5),
      keyBuilder: CacheOptions.defaultCacheKeyBuilder,
      allowPostMethod: false,
    );
    _dio.interceptors.add(DioCacheInterceptor(options: _cacheOptions));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        if (_token != null) {
          options.headers['Authorization'] = 'Bearer $_token';
        }
        if (kDebugMode) {
          debugPrint('[api] ${options.method} ${options.uri}');
        }
        return handler.next(options);
      },
      onResponse: (response, handler) async {
        final code = response.statusCode ?? 0;
        if (code >= 200 && code < 300) {
          return handler.next(response);
        }

        // 401 on a non-auth call — try to refresh the token once. If it
        // works, retry the original request. If it doesn't, fall through
        // to the normal error path which the router treats as a logout.
        if (code == 401 && _shouldAttemptRefresh(response.requestOptions)) {
          final ok = await _refreshOnce();
          if (ok) {
            try {
              final retry = await _dio.fetch<dynamic>(
                response.requestOptions..headers['Authorization'] = 'Bearer $_token',
              );
              return handler.resolve(retry);
            } catch (_) {
              // Fall through to error path with original 401.
            }
          } else {
            await _onAuthFailed?.call();
          }
        }

        return handler.reject(DioException(
          requestOptions: response.requestOptions,
          response: response,
          type: DioExceptionType.badResponse,
          error: _exceptionFromResponse(response),
        ));
      },
      onError: (err, handler) {
        // If we already wrapped it in onResponse, pass through. Otherwise
        // categorize the Dio failure (connection refused, timeout, etc.).
        if (err.error is ApiException) return handler.reject(err);
        return handler.reject(err.copyWith(error: _exceptionFromDio(err)));
      },
    ));
  }

  bool _shouldAttemptRefresh(RequestOptions opts) {
    // Don't refresh on the refresh / login endpoints themselves —
    // otherwise a failing refresh recurses forever.
    final path = opts.path;
    if (path.contains('/auth/refresh') || path.contains('/auth/login')) {
      return false;
    }
    if (_token == null) return false;
    if (_onTokenRefreshed == null) return false;
    return true;
  }

  /// Issue exactly one refresh request at a time. Concurrent callers
  /// share the same Future.
  Future<bool> _refreshOnce() {
    return _refreshInFlight ??= _doRefresh().whenComplete(() => _refreshInFlight = null);
  }

  Future<bool> _doRefresh() async {
    if (_token == null) return false;
    try {
      // Use a bare Dio instance so this call doesn't recurse back into
      // the 401 handler on the main client.
      final bare = Dio(BaseOptions(
        baseUrl: _baseUrl,
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $_token',
        },
        validateStatus: (_) => true,
      ));
      final r = await bare.post<dynamic>('/api/auth/refresh');
      if (r.statusCode == 200 && r.data is Map && (r.data as Map)['token'] is String) {
        final newToken = (r.data as Map)['token'] as String;
        _token = newToken;
        await _onTokenRefreshed?.call(newToken);
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  static final ApiClient instance = ApiClient._();
  late final Dio _dio;
  late CacheOptions _cacheOptions;
  String? _token;

  /// Set once at auth setup. Called when a 401 triggers a successful
  /// `/api/auth/refresh` — the auth provider persists the new token to
  /// secure storage so it survives a process restart.
  Future<void> Function(String newToken)? _onTokenRefreshed;

  /// Set once at auth setup. Called when refresh itself fails (token
  /// genuinely expired or revoked). The auth provider clears state and
  /// the router sends the user to /login.
  Future<void> Function()? _onAuthFailed;

  /// Concurrency lock: when many requests fire 401 at once we want a
  /// single in-flight refresh, not N.
  Future<bool>? _refreshInFlight;

  Dio get dio => _dio;

  void setToken(String token) => _token = token;
  void clearToken() {
    _token = null;
    // Flush the cache so the next user on this device can't read the
    // previous client's data from a stale entry.
    _cacheOptions.store?.clean(staleOnly: false);
  }

  /// Wire the two callbacks the auth provider needs. Call once during
  /// app startup, after the provider exists.
  void wireRefreshCallbacks({
    required Future<void> Function(String newToken) onTokenRefreshed,
    required Future<void> Function() onAuthFailed,
  }) {
    _onTokenRefreshed = onTokenRefreshed;
    _onAuthFailed     = onAuthFailed;
  }

  /// Base URL is build-time configurable via:
  ///   flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8002
  /// Default targets the dev server in `php artisan serve` mode.
  static const String _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8002',
  );
}

ApiException _exceptionFromResponse(Response<dynamic> resp) {
  final status = resp.statusCode ?? 0;
  final body   = resp.data;
  final msg    = (body is Map && body['message'] is String)
      ? body['message'] as String
      : 'Request failed (HTTP $status)';
  final code = switch (status) {
    401 => ApiErrorCode.unauthorized,
    403 => ApiErrorCode.forbidden,
    404 => ApiErrorCode.notFound,
    422 => ApiErrorCode.validation,
    429 => ApiErrorCode.rateLimited,
    >= 500 => ApiErrorCode.server,
    _ => ApiErrorCode.unknown,
  };
  return ApiException(code, msg, statusCode: status);
}

ApiException _exceptionFromDio(DioException err) {
  // Connection-level failures: phone offline, DNS, refused by server.
  switch (err.type) {
    case DioExceptionType.connectionError:
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.sendTimeout:
      return const ApiException(ApiErrorCode.network, 'No connection to the server.');
    case DioExceptionType.cancel:
      return const ApiException(ApiErrorCode.unknown, 'Request canceled.');
    default:
      return ApiException(ApiErrorCode.unknown, err.message ?? 'Unknown network error.');
  }
}
