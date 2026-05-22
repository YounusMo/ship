/// Payload for POST /api/v1/employee/scan/submit. Doubles as the on-disk
/// row in the outbox (sqflite) so we can re-submit verbatim after a
/// network interruption.
class ScanSubmitPayload {
  final String  stickerId;
  final String  eventType;        // InternalEventType enum value
  final int     branchId;
  final int?    shipmentPieceId;  // required when sticker is unassigned + first scan
  final int?    toBranchId;       // for IN_TRANSIT_INTERNAL hand-offs
  final String? notes;
  final String  clientEventId;    // ULID — server dedup safety net

  const ScanSubmitPayload({
    required this.stickerId,
    required this.eventType,
    required this.branchId,
    required this.shipmentPieceId,
    required this.toBranchId,
    required this.notes,
    required this.clientEventId,
  });

  Map<String, dynamic> toJson() => <String, dynamic>{
    'sticker_id'        : stickerId,
    'event_type'        : eventType,
    'branch_id'         : branchId,
    if (shipmentPieceId != null) 'shipment_piece_id' : shipmentPieceId,
    if (toBranchId != null)      'to_branch_id'      : toBranchId,
    if (notes != null && notes!.isNotEmpty) 'notes'  : notes,
    'client_event_id'   : clientEventId,
  };

  factory ScanSubmitPayload.fromJson(Map<String, dynamic> json) => ScanSubmitPayload(
    stickerId       : json['sticker_id']        as String,
    eventType       : json['event_type']        as String,
    branchId        : (json['branch_id']        as num).toInt(),
    shipmentPieceId : (json['shipment_piece_id'] as num?)?.toInt(),
    toBranchId      : (json['to_branch_id']     as num?)?.toInt(),
    notes           : json['notes']             as String?,
    clientEventId   : json['client_event_id']   as String,
  );
}
