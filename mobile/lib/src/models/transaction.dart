/// One row from `GET /api/transactions`. The backend already filters to
/// approved-only and scopes by the authenticated client, so the app can
/// render every row it receives without further gating.
class Transaction {
  final int     id;
  final String? transactionNumber;
  final int?    autoId;
  final String  type;        // 'deposit' | 'withdraw' | 'commission' | 'transfer'
  final String  currency;
  final double  value;
  final String? toCurrency;
  final double? transferValue;
  final String? purpose;
  final String? notes;
  final String  createdDate; // YYYY-MM-DD
  final String? createdTime; // HH:MM:SS
  final int?    branch;

  const Transaction({
    required this.id,
    required this.type,
    required this.currency,
    required this.value,
    required this.createdDate,
    this.transactionNumber,
    this.autoId,
    this.toCurrency,
    this.transferValue,
    this.purpose,
    this.notes,
    this.createdTime,
    this.branch,
  });

  factory Transaction.fromJson(Map<String, dynamic> json) => Transaction(
    id                : (json['id'] as num).toInt(),
    transactionNumber : json['transaction_number'] as String?,
    autoId            : (json['auto_id'] as num?)?.toInt(),
    type              : json['type'] as String,
    currency          : json['currency'] as String,
    value             : double.tryParse('${json['value']}') ?? 0,
    toCurrency        : json['to_currency']    as String?,
    transferValue     : json['transfer_value'] == null ? null : double.tryParse('${json['transfer_value']}'),
    purpose           : json['purpose']        as String?,
    notes             : json['notes']          as String?,
    createdDate       : json['created_date']   as String,
    createdTime       : json['created_time']   as String?,
    branch            : (json['branch'] as num?)?.toInt(),
  );

  /// Sign convention for the list row:
  ///   deposit, transfer_in  → +
  ///   withdraw, commission  → −
  ///   transfer (currency)   → both happen, label as "transfer"
  bool get isCredit => type == 'deposit';
  bool get isDebit  => type == 'withdraw' || type == 'commission';
}
