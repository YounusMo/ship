import 'shipment_piece.dart';
import 'tracking_timeline.dart';

/// One shipment with its pieces. Returned by `GET /api/shipments/{mode}/{id}`.
/// The `row` field is intentionally untyped (Map) because the backing table
/// changes shape between buckets — `received` rows come from store_*, `shipped`
/// rows from store_out_* — and we don't want to lose fields by forcing them
/// into a tight schema.
class ShipmentDetail {
  final String                mode;     // 'sea' | 'sky'
  final String                bucket;   // 'received' | 'shipped'
  final Map<String, dynamic>  row;
  final List<ShipmentPiece>   pieces;

  /// Unified tracking payload from the Phase 5a backend extension. Only
  /// populated for the `shipped` bucket; `null` for received rows that
  /// haven't been dispatched yet.
  final TrackingTimeline?     tracking;

  const ShipmentDetail({
    required this.mode,
    required this.bucket,
    required this.row,
    required this.pieces,
    required this.tracking,
  });

  factory ShipmentDetail.fromJson(Map<String, dynamic> json) => ShipmentDetail(
    mode     : json['mode']   as String,
    bucket   : json['bucket'] as String,
    row      : (json['row']   as Map<String, dynamic>?) ?? <String, dynamic>{},
    pieces   : ((json['pieces'] as List?) ?? const <dynamic>[])
        .whereType<Map<String, dynamic>>()
        .map(ShipmentPiece.fromJson)
        .toList(),
    tracking : json['tracking'] is Map<String, dynamic>
        ? TrackingTimeline.fromJson(json['tracking'] as Map<String, dynamic>)
        : null,
  );

  // Common row accessors — guarded with null-safety so a missing column
  // doesn't crash the detail screen.
  String? get transactionNumber => row['transaction_number'] as String?;
  int?    get clientId          => (row['client_id'] as num?)?.toInt();
  int?    get containerId       => (row['container_id'] as num?)?.toInt();
  int?    get pieces_           => (row['number'] as num?)?.toInt();
  double? get kg                => row['kg']  == null ? null : double.tryParse('${row['kg']}');
  double? get cbm               => row['cbm'] == null ? null : double.tryParse('${row['cbm']}');
  String? get category          => row['category'] as String?;
  String? get notes             => row['notes']    as String?;
  String? get shipFrom          => row['ship_from'] as String?;
  String? get createdDate       => row['created_date'] as String?;
  bool    get paymentPending    => row['payment_pending'] == true || row['payment_pending'] == 1 || row['payment_pending'] == '1';
}
