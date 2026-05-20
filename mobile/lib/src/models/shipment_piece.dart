/// One row from `shipment_pieces` (per the May-16 backend migration).
/// Each piece has its own QR tracking code that the mobile app can show
/// inside the shipment detail screen.
class ShipmentPiece {
  final int     id;
  final String  trackingCode;
  final String  sourceTable;
  final int     sourceId;
  final int     pieceIndex;
  final int     pieceTotal;
  final String? status;
  final int?    clientId;

  const ShipmentPiece({
    required this.id,
    required this.trackingCode,
    required this.sourceTable,
    required this.sourceId,
    required this.pieceIndex,
    required this.pieceTotal,
    this.status,
    this.clientId,
  });

  factory ShipmentPiece.fromJson(Map<String, dynamic> json) => ShipmentPiece(
    id           : (json['id'] as num).toInt(),
    trackingCode : json['tracking_code'] as String,
    sourceTable  : json['source_table']  as String,
    sourceId     : (json['source_id'] as num).toInt(),
    pieceIndex   : (json['piece_index'] as num?)?.toInt() ?? 0,
    pieceTotal   : (json['piece_total'] as num?)?.toInt() ?? 0,
    status       : json['status'] as String?,
    clientId     : (json['client_id'] as num?)?.toInt(),
  );
}
