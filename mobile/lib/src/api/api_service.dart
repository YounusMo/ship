import 'package:dio/dio.dart';

import '../models/balance.dart';
import '../models/client.dart';
import '../models/client_device.dart';
import '../models/notification_item.dart';
import '../models/paginated.dart';
import '../models/shipment.dart';
import '../models/shipment_detail.dart';
import '../models/transaction.dart';
import 'api_client.dart';

/// Typed wrapper over the backend's `/api/*` endpoints. Each method maps
/// one HTTP route → one Dart return type. JSON shaping happens in the
/// model `.fromJson` constructors so this layer stays declarative.
///
/// All methods throw [ApiException] on non-2xx — callers should let it
/// bubble up to the Riverpod AsyncNotifier, which surfaces it via
/// [AsyncValue.error].
class ApiService {
  ApiService(this._dio);
  final Dio _dio;

  // --- Auth -----------------------------------------------------------

  Future<({String token, Client client})> login({
    required String identifier,
    required String password,
  }) async {
    final resp = await _dio.post<Map<String, dynamic>>('/api/auth/login', data: {
      'identifier': identifier,
      'password'  : password,
    });
    final data = resp.data!;
    return (
      token : data['token'] as String,
      client: Client.fromJson(data['client'] as Map<String, dynamic>),
    );
  }

  Future<void> logout() async {
    await _dio.post<void>('/api/auth/logout');
  }

  Future<Client> me() async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/me');
    return Client.fromJson(resp.data!);
  }

  // --- Devices --------------------------------------------------------

  Future<void> registerDevice({
    required String platform, // 'ios' | 'android'
    required String token,
    String? appVersion,
    String? deviceModel,
    String? osVersion,
  }) async {
    await _dio.post<void>('/api/devices/register', data: {
      'platform'    : platform,
      'token'       : token,
      'app_version' : appVersion,
      'device_model': deviceModel,
      'os_version'  : osVersion,
    });
  }

  Future<List<ClientDevice>> devices() async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/devices');
    final rows = (resp.data?['data'] as List?) ?? const <dynamic>[];
    return rows
        .whereType<Map<String, dynamic>>()
        .map(ClientDevice.fromJson)
        .toList();
  }

  Future<void> revokeDeviceById(int id) async {
    await _dio.post<void>('/api/devices/$id/revoke');
  }

  // --- Data -----------------------------------------------------------

  Future<Balance> balances() async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/balances');
    return Balance.fromJson(resp.data!);
  }

  Future<Paginated<Transaction>> transactions({int page = 1, String? currency}) async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/transactions',
      queryParameters: <String, dynamic>{'page': page, if (currency != null) 'currency': currency},
    );
    return Paginated<Transaction>.fromJson(resp.data!, Transaction.fromJson);
  }

  Future<Paginated<Shipment>> shipments({int page = 1, String mode = 'all'}) async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/shipments',
      queryParameters: <String, dynamic>{'page': page, 'mode': mode},
    );
    return Paginated<Shipment>.fromJson(resp.data!, Shipment.fromJson);
  }

  Future<ShipmentDetail> shipmentDetail({required String mode, required int id}) async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/shipments/$mode/$id');
    return ShipmentDetail.fromJson(resp.data!);
  }

  Future<({int unreadCount, Paginated<NotificationItem> page})> notifications({int page = 1}) async {
    final resp = await _dio.get<Map<String, dynamic>>('/api/notifications',
      queryParameters: <String, dynamic>{'page': page},
    );
    final body = resp.data!;
    return (
      unreadCount: (body['unread_count'] as num?)?.toInt() ?? 0,
      page       : Paginated<NotificationItem>.fromJson(body, NotificationItem.fromJson),
    );
  }

  Future<void> markNotificationRead(String id) async {
    await _dio.post<void>('/api/notifications/$id/read');
  }

  Future<void> markAllNotificationsRead() async {
    await _dio.post<void>('/api/notifications/read-all');
  }
}

/// Single instance — bound to [ApiClient.instance.dio] so token changes
/// flow through automatically.
final apiService = ApiService(ApiClient.instance.dio);
