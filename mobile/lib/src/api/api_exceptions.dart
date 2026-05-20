/// Surface-level error type for the entire HTTP layer. Screens render
/// [message] directly; [code] is a stable identifier the UI can branch on
/// (e.g. show "Session expired, sign in again" for [ApiException.unauthorized]).
class ApiException implements Exception {
  final ApiErrorCode code;
  final String       message;
  final int?         statusCode;

  const ApiException(this.code, this.message, {this.statusCode});

  @override
  String toString() => 'ApiException($code, $statusCode): $message';
}

enum ApiErrorCode {
  network,        // connection refused, DNS, timeout
  unauthorized,   // 401 — token missing, expired, or wrong audience
  forbidden,      // 403 — token resolved to a non-client tokenable
  notFound,       // 404
  validation,     // 422
  rateLimited,    // 429
  server,         // 500+
  unknown,
}
