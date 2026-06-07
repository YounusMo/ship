import 'package:dio/dio.dart';

import '../models/activity_log_row.dart';
import '../models/scan_submit.dart';
import '../models/sticker_resolve.dart';
import 'api_client.dart';

/// Typed wrapper over the /api/v1/employee/* surface. Every method maps
/// the raw Response to a typed exception or a typed model — screens never
/// touch Dio directly.
class ApiService {
  ApiService(this._client);
  final ApiClient _client;

  Dio get _dio => _client.dio;

  /// Returns { token, abilities, user } on success.
  Future<Map<String, dynamic>> login({required String email, required String password, String? device}) async {
    final resp = await _dio.post('/api/v1/employee/auth/login', data: <String, dynamic>{
      'email'    : email,
      'password' : password,
      'device'   : ?device,
    });
    return ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
  }

  Future<void> logout() async {
    final resp = await _dio.post('/api/v1/employee/auth/logout');
    ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
  }

  Future<Map<String, dynamic>> me() async {
    final resp = await _dio.get('/api/v1/employee/me');
    return ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
  }

  Future<StickerResolveResult> scanResolve({String? stickerId, String? qrPayload}) async {
    final resp = await _dio.post('/api/v1/employee/scan/resolve', data: <String, dynamic>{
      'sticker_id' : ?stickerId,
      'qr_payload' : ?qrPayload,
    });
    // 404 unknown_sticker should still flow as a typed result, not an exception.
    final status = resp.statusCode ?? 0;
    final data = resp.data is Map<String, dynamic>
        ? resp.data as Map<String, dynamic>
        : <String, dynamic>{};
    if (status == 200) return StickerResolveResult.fromJson(data);
    if (status == 404) return StickerResolveResult.fromJson({...data, 'type': 'unknown_sticker'});
    if (status == 409) return StickerResolveResult.fromJson({...data, 'type': 'revoked_sticker'});
    throw ApiClient.defaultErrorMapper(status, data);
  }

  /// Returns the recorded tracking_event row on 201, OR throws a typed
  /// ApiValidationException whose `wireType` lets the caller distinguish
  /// 'invalid_transition' from 'unassigned_first_scan' from generic 422s.
  Future<Map<String, dynamic>> scanSubmit(ScanSubmitPayload payload) async {
    final resp = await _dio.post('/api/v1/employee/scan/submit', data: payload.toJson());
    return ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
  }

  Future<List<ActivityLogRow>> activity({int perPage = 25}) async {
    final resp = await _dio.get('/api/v1/employee/activity', queryParameters: <String, dynamic>{
      'per_page': perPage,
    });
    final body = ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
    final data = (body['data'] as List?) ?? const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(ActivityLogRow.fromJson)
        .toList();
  }

  Future<List<Map<String, dynamic>>> branchQueue(int branchId, {int limit = 100}) async {
    final resp = await _dio.get('/api/v1/employee/branches/$branchId/queue', queryParameters: {'limit': limit});
    final body = ApiClient.jsonOr(ApiClient.defaultErrorMapper, resp);
    final items = (body['items'] as List?) ?? const [];
    return items.whereType<Map<String, dynamic>>().toList();
  }
}
