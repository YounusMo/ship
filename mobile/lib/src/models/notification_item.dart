/// One row from `GET /api/notifications`. Laravel writes a JSON `data`
/// blob; we keep it as a generic Map so each notification class can
/// shape its own UI without forcing a new model per type.
class NotificationItem {
  final String              id;            // uuid
  final String              type;          // e.g. 'App\\Notifications\\ClientTransactionPosted'
  final Map<String, dynamic> data;
  final DateTime?           readAt;
  final DateTime            createdAt;

  const NotificationItem({
    required this.id,
    required this.type,
    required this.data,
    required this.createdAt,
    this.readAt,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) => NotificationItem(
    id        : json['id']   as String,
    type      : json['type'] as String,
    data      : (json['data'] as Map<String, dynamic>?) ?? <String, dynamic>{},
    readAt    : json['read_at']    == null ? null : DateTime.tryParse(json['read_at'] as String),
    createdAt : DateTime.tryParse(json['created_at'] as String? ?? '') ?? DateTime.now(),
  );

  bool get isUnread => readAt == null;

  /// The backend tags each notification with a `category` in its data
  /// payload — used by the row widget to pick an icon and by the deep-link
  /// handler to pick the right screen on tap.
  String get category => (data['category'] as String?) ?? 'unknown';
}
