import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

const _biometricEnabledKey = 'shipflow.biometric.enabled';
const _biometricReasonKey  = 'shipflow.biometric.reason';

/// Biometric-unlock gate. The flow:
///
///   1. First successful login → setEnabled(true) so the next cold start
///      requires Face/Touch ID before showing the dashboard.
///   2. On cold start, if a token AND biometrics are enabled, the splash
///      screen calls authenticate() before letting the router proceed.
///   3. Logout → clear the flag.
///
/// We rely on local_auth's "deviceCredential" fallback so users who don't
/// have biometrics enrolled can still unlock with their device passcode.
class BiometricController {
  final _auth    = LocalAuthentication();
  final _storage = const FlutterSecureStorage();

  Future<bool> isAvailable() async {
    try {
      final supported = await _auth.isDeviceSupported();
      if (!supported) return false;
      final canCheck = await _auth.canCheckBiometrics;
      return canCheck;
    } catch (_) {
      return false;
    }
  }

  Future<bool> isEnabled() async =>
      (await _storage.read(key: _biometricEnabledKey)) == '1';

  Future<void> setEnabled(bool enabled) =>
      _storage.write(key: _biometricEnabledKey, value: enabled ? '1' : '0');

  /// Cache a locale-aware prompt string so the next cold-start biometric
  /// gate (which runs in AuthNotifier with no BuildContext) can read it
  /// from secure storage. Called from any screen that has a BuildContext:
  /// login on successful sign-in, settings on the biometric toggle.
  Future<void> cacheLocalizedReason(String reason) =>
      _storage.write(key: _biometricReasonKey, value: reason);

  /// Returns true when the user successfully authenticated, false when
  /// they canceled or the platform rejected it.
  ///
  /// Reason precedence: explicit param > previously-cached localized
  /// string > English fallback. The English fallback only surfaces on a
  /// brand-new install where the user enabled biometrics before any
  /// localized prompt path had a chance to populate the cache — rare in
  /// practice because the enable toggle itself runs from a screen with
  /// context.
  Future<bool> authenticate({String? reason}) async {
    final cached  = await _storage.read(key: _biometricReasonKey);
    final prompt  = reason ?? cached ?? 'Unlock ShipFlow';
    try {
      return await _auth.authenticate(
        localizedReason: prompt,
        options: const AuthenticationOptions(
          biometricOnly: false,        // allow passcode fallback
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } catch (_) {
      return false;
    }
  }
}

final biometricControllerProvider = Provider<BiometricController>((_) => BiometricController());
