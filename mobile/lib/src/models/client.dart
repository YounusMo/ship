/// The authenticated client profile (`GET /api/me`). Plain data class —
/// no codegen so the file is easy to read at PR time.
class Client {
  final int     id;
  final String  code;
  final String  name;
  final String? email;
  final String? phone;
  final String? lang;

  const Client({
    required this.id,
    required this.code,
    required this.name,
    this.email,
    this.phone,
    this.lang,
  });

  factory Client.fromJson(Map<String, dynamic> json) => Client(
    id    : (json['id'] as num).toInt(),
    code  : json['code'] as String,
    name  : json['name'] as String,
    email : json['email'] as String?,
    phone : json['phone'] as String?,
    lang  : json['lang']  as String?,
  );
}
