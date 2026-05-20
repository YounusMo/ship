import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

const _biometricEnabledKey = 'shipflow.biometric.enabled';

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

  /// Returns true when the user successfully authenticated, false when
  /// they canceled, and rethrows on platform errors so callers can
  /// degrade gracefully (e.g. fall back to forcing a re-login).
  Future<bool> authenticate({String reason = 'Confirm it is you'}) async {
    try {
      return await _auth.authenticate(
        localizedReason: reason,
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
