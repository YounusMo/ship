/// Per-category opt-in. Matches `GET /api/notifications/prefs` exactly.
/// Stored server-side on `clients.notify_*` so a mute on one phone
/// silences the user's other devices too.
class NotificationPrefs {
  final bool transactions;
  final bool shipments;
  final bool receipts;

  const NotificationPrefs({
    required this.transactions,
    required this.shipments,
    required this.receipts,
  });

  factory NotificationPrefs.fromJson(Map<String, dynamic> json) => NotificationPrefs(
    transactions: (json['transactions'] as bool?) ?? true,
    shipments   : (json['shipments']    as bool?) ?? true,
    receipts    : (json['receipts']     as bool?) ?? true,
  );

  NotificationPrefs copyWith({bool? transactions, bool? shipments, bool? receipts}) =>
      NotificationPrefs(
        transactions: transactions ?? this.transactions,
        shipments   : shipments    ?? this.shipments,
        receipts    : receipts     ?? this.receipts,
      );
}
