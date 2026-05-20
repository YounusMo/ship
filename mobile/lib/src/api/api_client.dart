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

    // Memory cache for GET responses. Short TTL — we still want a fresh
    // balance after a deposit, but two consecutive list pulls within ~30s
    // can be served from RAM (typical of tab-switching). The token
    // interceptor sits in front so a logout invalidates implicitly via
    // the changed Authorization header (cache key includes the URI but
    // not headers, so we flush on logout below).
    final cacheStore = MemCacheStore(maxSize: 5 * 1024 * 1024); // 5 MiB
    _cacheOptions = CacheOptions(
      store: cacheStore,
      policy: CachePolicy.request,
      hitCacheOnErrorExcept: const <int>[401, 403],
      maxStale: const Duration(seconds: 30),
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
      onResponse: (response, handler) {
        final code = response.statusCode ?? 0;
        if (code >= 200 && code < 300) {
          return handler.next(response);
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

  static final ApiClient instance = ApiClient._();
  late final Dio _dio;
  late final CacheOptions _cacheOptions;
  String? _token;

  Dio get dio => _dio;

  void setToken(String token) => _token = token;
  void clearToken() {
    _token = null;
    // Flush the cache so the next user on this device can't read the
    // previous client's data from a stale entry.
    _cacheOptions.store?.clean(staleOnly: false);
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
