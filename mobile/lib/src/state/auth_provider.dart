import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/api_client.dart';
import '../api/api_service.dart';
import '../models/client.dart';
import 'biometric_provider.dart';

const _tokenKey  = 'shipflow.token';
const _clientKey = 'shipflow.client.id';

/// Authenticated session state. Built on AsyncNotifier so the splash
/// screen can branch on `isLoading` while we restore the token from
/// secure storage on cold start.
class AuthNotifier extends AsyncNotifier<Client?> {
  final _storage = const FlutterSecureStorage();

  @override
  Future<Client?> build() async {
    // Wire token-refresh + auth-fail callbacks into the ApiClient
    // exactly once. The interceptor in ApiClient calls these on 401
    // — see `wireRefreshCallbacks` and gap #4 in docs/GAPS.md.
    ApiClient.instance.wireRefreshCallbacks(
      onTokenRefreshed: (newToken) async {
        await _storage.write(key: _tokenKey, value: newToken);
      },
      onAuthFailed: () async {
        await _storage.delete(key: _tokenKey);
        await _storage.delete(key: _clientKey);
        ApiClient.instance.clearToken();
        state = const AsyncValue.data(null);
      },
    );

    final token = await _storage.read(key: _tokenKey);
    if (token == null) return null;

    // Biometric gate: if the user enabled Face/Touch ID after a previous
    // login, prompt before re-hydrating the session. Failure to authenticate
    // is treated like a missing token — the user lands on /login.
    //
    // The prompt string is sourced from a previously-cached localized
    // version (see BiometricController.cacheLocalizedReason), which the
    // login + settings screens populate on every successful entry. On a
    // brand-new install the cache is empty and the controller falls back
    // to English; in practice that path is unreachable because enabling
    // biometrics in the first place runs from a screen with a context.
    final bio = ref.read(biometricControllerProvider);
    if (await bio.isEnabled() && await bio.isAvailable()) {
      final ok = await bio.authenticate();
      if (!ok) return null;
    }

    // We have a token; ask the server to confirm it's still valid.
    // A 401 here means the token was revoked (logout from another device,
    // password reset, etc.) — clear and present login.
    ApiClient.instance.setToken(token);
    try {
      return await apiService.me();
    } catch (_) {
      await _storage.delete(key: _tokenKey);
      await _storage.delete(key: _clientKey);
      ApiClient.instance.clearToken();
      return null;
    }
  }

  /// Login → persist token → flip auth state. The router redirect picks
  /// up the change via the Provider.listen on this notifier.
  Future<void> login({required String identifier, required String password}) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final result = await apiService.login(identifier: identifier, password: password);
      await _storage.write(key: _tokenKey,  value: result.token);
      await _storage.write(key: _clientKey, value: '${result.client.id}');
      ApiClient.instance.setToken(result.token);

      // Enable biometric unlock by default after the first successful
      // login on a device that supports it. Users can disable from a
      // settings screen later — surfacing that switch is a follow-up.
      final bio = ref.read(biometricControllerProvider);
      if (await bio.isAvailable() && !(await bio.isEnabled())) {
        await bio.setEnabled(true);
      }
      return result.client;
    });
  }

  /// Logout end-to-end: revoke server-side, drop local creds, clear state.
  /// We swallow server errors deliberately — even if the API call fails
  /// (offline, server down), we still want the device to forget the token.
  Future<void> logout() async {
    try { await apiService.logout(); } catch (_) {}
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _clientKey);
    ApiClient.instance.clearToken();
    // Clear the biometric opt-in so the next user on this device isn't
    // prompted for OUR Face ID on their splash screen.
    await ref.read(biometricControllerProvider).setEnabled(false);
    state = const AsyncValue.data(null);
  }
}

final authProvider = AsyncNotifierProvider<AuthNotifier, Client?>(AuthNotifier.new);
