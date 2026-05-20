/// Per-currency balance returned by `GET /api/balances`. Sourced
/// server-side from journal_lines (the post-audit canonical source),
/// so these numbers match what `/accounting/balance-sheet` would
/// produce for this client.
class Balance {
  final double usd;
  final double eur;
  final double den;  // Libyan dinar
  final double cny;
  final DateTime asOf;

  const Balance({
    required this.usd,
    required this.eur,
    required this.den,
    required this.cny,
    required this.asOf,
  });

  factory Balance.fromJson(Map<String, dynamic> json) => Balance(
    usd : (json['usd'] as num?)?.toDouble() ?? 0,
    eur : (json['eur'] as num?)?.toDouble() ?? 0,
    den : (json['den'] as num?)?.toDouble() ?? 0,
    cny : (json['cny'] as num?)?.toDouble() ?? 0,
    asOf: DateTime.tryParse(json['as_of'] as String? ?? '') ?? DateTime.now(),
  );

  /// Useful for laying out the dashboard cards in a stable order.
  Map<String, double> get asMap => <String, double>{
    'USD': usd,
    'EUR': eur,
    'LYD': den, // backend code is `den`; brand-facing label is LYD.
    'CNY': cny,
  };
}
