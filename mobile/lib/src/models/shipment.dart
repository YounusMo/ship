/// One row from `GET /api/shipments`. The backend unions store_* (received)
/// and store_out_* (shipped) and tags each row with `mode` (sea|sky) and
/// `bucket` (received|shipped) so the UI can branch on a single row type.
class Shipment {
  final int     id;
  final String  mode;     // 'sea' | 'sky'
  final String  bucket;   // 'received' | 'shipped'
  final String? transactionNumber;
  final int?    clientId;
  final int?    containerId;
  final int?    number;
  final double? kg;
  final double? cbm;
  final String? type;
  final String? category;
  final String? notes;
  final String? shipFrom;
  final bool    paymentPending;  // only meaningful when bucket == 'shipped'
  final String? createdDate;

  const Shipment({
    required this.id,
    required this.mode,
    required this.bucket,
    this.transactionNumber,
    this.clientId,
    this.containerId,
    this.number,
    this.kg,
    this.cbm,
    this.type,
    this.category,
    this.notes,
    this.shipFrom,
    this.paymentPending = false,
    this.createdDate,
  });

  factory Shipment.fromJson(Map<String, dynamic> json) {
    // Same TEXT-column gotcha as Transaction.fromJson — coerce defensively.
    int?    asInt(Object? v)    => v == null ? null : int.tryParse('$v');
    double? asDouble(Object? v) => v == null ? null : double.tryParse('$v');
    final pp = json['payment_pending'];
    return Shipment(
      id                : asInt(json['id']) ?? 0,
      mode              : json['mode']   as String,
      bucket            : json['bucket'] as String,
      transactionNumber : json['transaction_number'] as String?,
      clientId          : asInt(json['client_id']),
      containerId       : asInt(json['container_id']),
      number            : asInt(json['number']),
      kg                : asDouble(json['kg']),
      cbm               : asDouble(json['cbm']),
      type              : json['type']     as String?,
      category          : json['category'] as String?,
      notes             : json['notes']    as String?,
      shipFrom          : json['ship_from']as String?,
      paymentPending    : pp == true || pp == 1 || pp == '1',
      createdDate       : json['created_date'] as String?,
    );
  }

  bool get isSea => mode == 'sea';
  bool get isSky => mode == 'sky';
}
