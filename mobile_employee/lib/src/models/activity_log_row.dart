/// One row from GET /api/v1/employee/activity (employee_action_logs table).
class ActivityLogRow {
  final int        id;
  final int?       branchId;
  final String     action;
  final String?    entityType;   // e.g. 'store_out_sea'
  final String?    entityId;
  final DateTime?  createdAt;
  final Map<String, dynamic> payload;

  const ActivityLogRow({
    required this.id,
    required this.branchId,
    required this.action,
    required this.entityType,
    required this.entityId,
    required this.createdAt,
    required this.payload,
  });

  factory ActivityLogRow.fromJson(Map<String, dynamic> json) => ActivityLogRow(
    id          : (json['id'] as num).toInt(),
    branchId    : (json['branch_id'] as num?)?.toInt(),
    action      : (json['action'] as String?) ?? '',
    entityType  : json['entity_type'] as String?,
    entityId    : json['entity_id']   as String?,
    createdAt   : _parseDate(json['created_at']),
    payload     : (json['payload'] as Map<String, dynamic>?) ?? const {},
  );

  static DateTime? _parseDate(dynamic v) {
    if (v is String && v.isNotEmpty) {
      try { return DateTime.parse(v).toLocal(); } catch (_) { return null; }
    }
    return null;
  }
}
