/// Result of POST /api/v1/employee/scan/resolve. Three shapes depending
/// on the sticker's state — `type` is the discriminator.
sealed class StickerResolveResult {
  const StickerResolveResult();

  /// Wire types from the backend: 'unassigned' | 'assigned' | 'revoked_sticker' | 'unknown_sticker'.
  String get wireType;

  factory StickerResolveResult.fromJson(Map<String, dynamic> json) {
    final type = (json['type'] as String?) ?? 'unknown_sticker';
    return switch (type) {
      'assigned'    => StickerResolveAssigned.fromJson(json),
      'unassigned'  => StickerResolveUnassigned.fromJson(json),
      'revoked_sticker' => StickerResolveRevoked.fromJson(json),
      'unknown_sticker' => StickerResolveNotFound.fromJson(json),
      _ => StickerResolveNotFound(stickerId: (json['sticker_id'] as String?) ?? ''),
    };
  }
}

class StickerResolveAssigned extends StickerResolveResult {
  final String        stickerId;
  final int?          pieceId;
  final String?       sourceTable;     // e.g. store_out_sea
  final int?          sourceId;
  final String?       currentEventType;
  final List<String>  allowedEventTypes;

  const StickerResolveAssigned({
    required this.stickerId,
    required this.pieceId,
    required this.sourceTable,
    required this.sourceId,
    required this.currentEventType,
    required this.allowedEventTypes,
  });

  @override
  String get wireType => 'assigned';

  factory StickerResolveAssigned.fromJson(Map<String, dynamic> json) {
    final sticker = (json['sticker'] as Map<String, dynamic>?) ?? const {};
    final piece   = (json['piece']   as Map<String, dynamic>?) ?? const {};
    return StickerResolveAssigned(
      stickerId         : (sticker['id'] as String?) ?? '',
      pieceId           : (piece['id']           as num?)?.toInt(),
      sourceTable       : piece['source_table']  as String?,
      sourceId          : (piece['source_id']    as num?)?.toInt(),
      currentEventType  : json['current_event_type'] as String?,
      allowedEventTypes : ((json['allowed_event_types'] as List?) ?? const <dynamic>[])
          .whereType<String>().toList(),
    );
  }
}

class StickerResolveUnassigned extends StickerResolveResult {
  final String       stickerId;
  final List<String> allowedEventTypes;

  const StickerResolveUnassigned({
    required this.stickerId,
    required this.allowedEventTypes,
  });

  @override
  String get wireType => 'unassigned';

  factory StickerResolveUnassigned.fromJson(Map<String, dynamic> json) {
    final sticker = (json['sticker'] as Map<String, dynamic>?) ?? const {};
    return StickerResolveUnassigned(
      stickerId         : (sticker['id'] as String?) ?? '',
      allowedEventTypes : ((json['allowed_event_types'] as List?) ?? const <dynamic>[])
          .whereType<String>().toList(),
    );
  }
}

class StickerResolveRevoked extends StickerResolveResult {
  final String stickerId;
  const StickerResolveRevoked({required this.stickerId});

  @override
  String get wireType => 'revoked_sticker';

  factory StickerResolveRevoked.fromJson(Map<String, dynamic> json) {
    final sticker = (json['sticker'] as Map<String, dynamic>?) ?? const {};
    return StickerResolveRevoked(stickerId: (sticker['id'] as String?) ?? '');
  }
}

class StickerResolveNotFound extends StickerResolveResult {
  final String stickerId;
  const StickerResolveNotFound({required this.stickerId});

  @override
  String get wireType => 'unknown_sticker';

  factory StickerResolveNotFound.fromJson(Map<String, dynamic> json) =>
      StickerResolveNotFound(stickerId: (json['sticker_id'] as String?) ?? '');
}
