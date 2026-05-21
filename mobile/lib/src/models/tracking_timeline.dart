/// Customer-facing unified tracking payload returned under
/// `GET /api/shipments/{mode}/{id}` as `tracking`. The backend merges
/// container-level INTERNATIONAL events (ShipsGo) with shipment-level
/// INTERNAL events (employee scans) into one chronological stream and
/// strips raw provider names + PII, so the client just renders.
class TrackingTimeline {
  /// One of the StatusComputer-derived codes (AT_ORIGIN, IN_TRANSIT_INTL,
  /// AT_PORT, AT_HUB, IN_TRANSIT_INTERNAL, AT_DESTINATION,
  /// READY_FOR_PICKUP, DELIVERED, EXCEPTION). Free-form string so a new
  /// backend status doesn't require a client release.
  final String status;
  final List<TrackingEvent> events;
  final int internationalCount;
  final int internalCount;

  const TrackingTimeline({
    required this.status,
    required this.events,
    required this.internationalCount,
    required this.internalCount,
  });

  bool get isEmpty => events.isEmpty;

  factory TrackingTimeline.fromJson(Map<String, dynamic> json) {
    final counts = (json['counts'] as Map<String, dynamic>?) ?? const {};
    return TrackingTimeline(
      status              : (json['status'] as String?) ?? 'AT_ORIGIN',
      internationalCount  : (counts['international'] as num?)?.toInt() ?? 0,
      internalCount       : (counts['internal']      as num?)?.toInt() ?? 0,
      events              : ((json['timeline'] as List?) ?? const <dynamic>[])
          .whereType<Map<String, dynamic>>()
          .map(TrackingEvent.fromJson)
          .toList(),
    );
  }
}

/// One row in the unified timeline. `message` is the backend-rendered
/// localized string (the client doesn't translate); fall back to the
/// raw event_type for display when no translation key was wired up.
class TrackingEvent {
  final int     id;
  final String  kind;        // 'INTERNATIONAL' | 'INTERNAL'
  final String  eventType;   // raw code, e.g. GATE_IN, READY_FOR_PICKUP
  final DateTime? occurredAt;
  final String? city;
  final String? country;
  final String? message;
  final int?    branchId;

  const TrackingEvent({
    required this.id,
    required this.kind,
    required this.eventType,
    required this.occurredAt,
    required this.city,
    required this.country,
    required this.message,
    required this.branchId,
  });

  bool get isInternational => kind == 'INTERNATIONAL';
  bool get isInternal      => kind == 'INTERNAL';

  String get locationLabel {
    final parts = <String>[
      if (city != null && city!.isNotEmpty) city!,
      if (country != null && country!.isNotEmpty) country!,
    ];
    return parts.join(', ');
  }

  factory TrackingEvent.fromJson(Map<String, dynamic> json) => TrackingEvent(
    id          : (json['id'] as num?)?.toInt() ?? 0,
    kind        : (json['kind'] as String?) ?? 'INTERNATIONAL',
    eventType   : (json['event_type'] as String?) ?? '',
    occurredAt  : _parseDate(json['occurred_at']),
    city        : json['city']    as String?,
    country     : json['country'] as String?,
    message     : json['message'] as String?,
    branchId    : (json['branch_id'] as num?)?.toInt(),
  );

  static DateTime? _parseDate(dynamic v) {
    if (v is String && v.isNotEmpty) {
      try { return DateTime.parse(v).toLocal(); } catch (_) { return null; }
    }
    return null;
  }
}
