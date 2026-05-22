/// A ShipFlow branch the employee can act at.
class Branch {
  final int     id;
  final String  code;
  final String  name;
  final String? nameEn;
  final String  role;      // HUB / SPOKE / ADMIN
  final String  city;
  final String? country;

  const Branch({
    required this.id,
    required this.code,
    required this.name,
    required this.nameEn,
    required this.role,
    required this.city,
    required this.country,
  });

  factory Branch.fromJson(Map<String, dynamic> json) => Branch(
    id      : (json['id'] as num).toInt(),
    code    : (json['code'] as String?) ?? '',
    name    : (json['name'] as String?) ?? (json['name_en'] as String?) ?? '',
    nameEn  : json['name_en'] as String?,
    role    : (json['role'] as String?) ?? 'SPOKE',
    city    : (json['city'] as String?) ?? '',
    country : json['country'] as String?,
  );
}
