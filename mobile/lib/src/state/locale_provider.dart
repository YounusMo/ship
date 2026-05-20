import 'dart:ui';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const _localeKey = 'shipflow.locale';

/// User's preferred locale, persisted to secure storage. `null` means
/// "follow the system locale" — the default for a fresh install.
///
/// MaterialApp's `locale:` reads this; the settings screen writes it.
class LocaleNotifier extends AsyncNotifier<Locale?> {
  final _storage = const FlutterSecureStorage();

  @override
  Future<Locale?> build() async {
    final raw = await _storage.read(key: _localeKey);
    if (raw == null || raw.isEmpty) return null;
    return Locale(raw);
  }

  Future<void> setLocale(Locale? locale) async {
    if (locale == null) {
      await _storage.delete(key: _localeKey);
    } else {
      await _storage.write(key: _localeKey, value: locale.languageCode);
    }
    state = AsyncValue.data(locale);
  }
}

final localeProvider = AsyncNotifierProvider<LocaleNotifier, Locale?>(LocaleNotifier.new);
