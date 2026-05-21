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

  factory Transaction.fromJson(Map<String, dynamic> json) {
    // Several backend columns (value, auto_id, branch, etc.) are MySQL TEXT
    // even though they hold numbers — the API surfaces them as JSON strings.
    // Coerce defensively so the list doesn't crash on any one row.
    int?    asInt(Object? v)    => v == null ? null : int.tryParse('$v');
    double? asDouble(Object? v) => v == null ? null : double.tryParse('$v');
    return Transaction(
      id                : (asInt(json['id']) ?? 0),
      transactionNumber : json['transaction_number'] as String?,
      autoId            : asInt(json['auto_id']),
      type              : json['type'] as String,
      currency          : json['currency'] as String,
      value             : asDouble(json['value']) ?? 0,
      toCurrency        : json['to_currency'] as String?,
      transferValue     : asDouble(json['transfer_value']),
      purpose           : json['purpose']     as String?,
      notes             : json['notes']       as String?,
      createdDate       : json['created_date']as String,
      createdTime       : json['created_time']as String?,
      branch            : asInt(json['branch']),
    );
  }

  /// Sign convention for the list row:
  ///   deposit, transfer_in  → +
  ///   withdraw, commission  → −
  ///   transfer (currency)   → both happen, label as "transfer"
  bool get isCredit => type == 'deposit';
  bool get isDebit  => type == 'withdraw' || type == 'commission';
}
