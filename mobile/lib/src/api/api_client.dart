import 'package:dio/dio.dart';
import 'package:dio_cache_interceptor/dio_cache_interceptor.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';

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

    // Disk-backed cache for GET responses. Survives cold starts, so the
    // dashboard renders the last balances even when the phone has no
    // network. Initialized lazily because path_provider returns a Future;
    // until then, requests go straight through.
    //
    // The token interceptor sits behind the cache so a logged-out cache
    // hit still attaches no Authorization header. clearToken() flushes
    // the whole store on logout to prevent the next user on this device
    // from reading the previous client's data.
    _cacheOptions = CacheOptions(
      store: MemCacheStore(maxSize: 5 * 1024 * 1024), // bootstrap value
      policy: CachePolicy.request,
      hitCacheOnErrorExcept: const <int>[401, 403],
      maxStale: const Duration(minutes: 5),
      keyBuilder: CacheOptions.defaultCacheKeyBuilder,
      allowPostMethod: false,
    );
    _dio.interceptors.add(DioCacheInterceptor(options: _cacheOptions));
    _upgradeToDiskCache();

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
  late CacheOptions _cacheOptions;
  String? _token;

  /// Swap the bootstrap MemCacheStore for a disk-backed DbCacheStore so
  /// future requests use it. Failures (e.g. sandboxed environments where
  /// path_provider can't resolve a writable directory) fall back to the
  /// in-memory store silently — better degraded perf than a crash.
  Future<void> _upgradeToDiskCache() async {
    try {
      final dir = await getApplicationDocumentsDirectory();
      _cacheOptions = _cacheOptions.copyWith(
        store: FileCacheStore('${dir.path}/shipflow_cache'),
      );
      // Replace the interceptor in place. The old MemCacheStore garbage
      // collects on its own once nothing references it.
      _dio.interceptors.removeWhere((i) => i is DioCacheInterceptor);
      _dio.interceptors.insert(0, DioCacheInterceptor(options: _cacheOptions));
    } catch (e) {
      debugPrint('[api] disk cache upgrade failed, keeping in-memory: $e');
    }
  }

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
