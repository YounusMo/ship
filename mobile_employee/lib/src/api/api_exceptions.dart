/// Typed wrappers over Dio errors so screens can pattern-match.
sealed class ApiException implements Exception {
  final String message;
  const ApiException(this.message);
  @override String toString() => 'ApiException: $message';
}

class ApiNetworkException extends ApiException {
  const ApiNetworkException(super.message);
}

class ApiAuthException extends ApiException {
  const ApiAuthException(super.message);
}

class ApiForbiddenException extends ApiException {
  /// Set by the server's `type` field — e.g. 'branch_scope_denied'.
  final String? wireType;
  const ApiForbiddenException(super.message, {this.wireType});
}

class ApiValidationException extends ApiException {
  /// 422 type from the backend — e.g. 'invalid_transition', 'unassigned_first_scan'.
  final String? wireType;
  const ApiValidationException(super.message, {this.wireType});
}

class ApiNotFoundException extends ApiException {
  const ApiNotFoundException(super.message);
}

class ApiConflictException extends ApiException {
  final String? wireType;
  const ApiConflictException(super.message, {this.wireType});
}

class ApiRateLimitedException extends ApiException {
  final int retryAfterSeconds;
  const ApiRateLimitedException(super.message, {required this.retryAfterSeconds});
}

class ApiServerException extends ApiException {
  final int status;
  const ApiServerException(super.message, {required this.status});
}
