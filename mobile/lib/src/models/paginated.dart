/// Laravel paginator envelope — same shape across every list endpoint.
/// Generic so we can re-use it for Transaction, Shipment, Notification, ...
class Paginated<T> {
  final List<T> data;
  final int     currentPage;
  final int     perPage;
  final int     total;

  const Paginated({
    required this.data,
    required this.currentPage,
    required this.perPage,
    required this.total,
  });

  factory Paginated.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) itemFromJson,
  ) {
    final raw = (json['data'] as List?) ?? const <dynamic>[];
    return Paginated<T>(
      data: raw
          .whereType<Map<String, dynamic>>()
          .map(itemFromJson)
          .toList(),
      currentPage : (json['current_page'] as num?)?.toInt() ?? 1,
      perPage     : (json['per_page']     as num?)?.toInt() ?? raw.length,
      total       : (json['total']        as num?)?.toInt() ?? raw.length,
    );
  }

  bool get hasMore => currentPage * perPage < total;
}
