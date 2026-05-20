/// One row from `GET /api/devices` — the authenticated client's currently
/// active push registrations. The token itself is intentionally NOT in this
/// payload; the only operation the app needs is "revoke by id".
class ClientDevice {
  final int       id;
  final String    platform;     // 'ios' | 'android' | 'web'
  final String?   deviceModel;
  final String?   osVersion;
  final String?   appVersion;
  final DateTime? lastSeenAt;
  final DateTime? createdAt;

  const ClientDevice({
    required this.id,
    required this.platform,
    this.deviceModel,
    this.osVersion,
    this.appVersion,
    this.lastSeenAt,
    this.createdAt,
  });

  factory ClientDevice.fromJson(Map<String, dynamic> json) => ClientDevice(
    id          : (json['id'] as num).toInt(),
    platform    : json['platform']     as String,
    deviceModel : json['device_model'] as String?,
    osVersion   : json['os_version']   as String?,
    appVersion  : json['app_version']  as String?,
    lastSeenAt  : json['last_seen_at'] == null ? null : DateTime.tryParse(json['last_seen_at'] as String),
    createdAt   : json['created_at']   == null ? null : DateTime.tryParse(json['created_at']   as String),
  );

  String get displayName {
    if ((deviceModel ?? '').isNotEmpty) return deviceModel!;
    return platform == 'ios' ? 'iPhone / iPad' : platform == 'android' ? 'Android device' : 'Browser';
  }
}
