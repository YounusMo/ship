import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/api_client.dart';
import '../api/api_exceptions.dart';
import '../api/api_service.dart';
import '../models/employee.dart';
import '../outbox/outbox_drainer.dart';
import '../outbox/outbox_store.dart';
import 'auth_state.dart';

/// Default API base — falls back to localhost for dev. Override at build
/// time with `--dart-define=API_BASE_URL=https://...` or via SettingsScreen.
const String _kDefaultApiBase = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://127.0.0.1:8002',
);

/// Persisted config (base URL, last-used active branch) lives in
/// flutter_secure_storage so it survives app restarts and is at least
/// encrypted at rest on both iOS and Android.
final secureStorageProvider = Provider<FlutterSecureStorage>(
  (ref) => const FlutterSecureStorage(),
);

/// Configurable at runtime via SettingsScreen.
final apiBaseUrlProvider = StateProvider<String>((ref) => _kDefaultApiBase);

/// The current auth state. The router watches this.
final authControllerProvider = AsyncNotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

class AuthController extends AsyncNotifier<AuthState> {
  @override
  Future<AuthState> build() async {
    final storage = ref.watch(secureStorageProvider);
    final token = await storage.read(key: 'auth_token');
    if (token == null || token.isEmpty) return const AuthSignedOut();

    final abilitiesCsv = await storage.read(key: 'auth_abilities') ?? '';
    final abilities = abilitiesCsv.split(',').where((s) => s.isNotEmpty).toList();
    final activeBranchIdStr = await storage.read(key: 'active_branch_id');
    final activeBranchId = int.tryParse(activeBranchIdStr ?? '');

    // Rehydrate the employee profile by calling /me with the cached token.
    final api = _service(token);
    try {
      final me = await api.me();
      final emp = Employee.fromMe(me, abilities);
      return AuthSignedIn(token: token, employee: emp, activeBranchId: activeBranchId);
    } on ApiAuthException {
      // Token rejected — wipe and force a re-login.
      await storage.deleteAll();
      return const AuthSignedOut();
    } catch (e) {
      // Network down on boot: keep what we know so the app can show offline
      // outbox + last-known activity. Fall back to a synthetic Employee
      // built from the cached abilities (no name/email).
      debugPrint('auth boot: $e — staying signed in offline');
      return AuthSignedIn(
        token: token,
        employee: Employee(
          id: 0, name: '', email: '', assignments: const [], abilities: abilities,
        ),
        activeBranchId: activeBranchId,
      );
    }
  }

  ApiService _service(String? token) {
    final base = ref.read(apiBaseUrlProvider);
    return ApiService(ApiClient(baseUrl: base, tokenProvider: () => token));
  }

  Future<void> signIn({required String email, required String password}) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      final api = _service(null);
      final resp = await api.login(email: email, password: password, device: 'employee-app');
      final token = resp['token'] as String;
      final abilities = ((resp['abilities'] as List?) ?? const [])
          .whereType<String>().toList();
      final storage = ref.read(secureStorageProvider);
      await storage.write(key: 'auth_token', value: token);
      await storage.write(key: 'auth_abilities', value: abilities.join(','));

      final me = await _service(token).me();
      final emp = Employee.fromMe(me, abilities);

      // Auto-pick a default active branch — the first one the user is
      // assigned to. Settings can change it later.
      final defaultBranchId = emp.assignments.isNotEmpty
          ? emp.assignments.first.branch.id : null;
      if (defaultBranchId != null) {
        await storage.write(key: 'active_branch_id', value: defaultBranchId.toString());
      }
      return AuthSignedIn(token: token, employee: emp, activeBranchId: defaultBranchId);
    });
  }

  Future<void> setActiveBranch(int? branchId) async {
    final cur = state.value;
    if (cur is! AuthSignedIn) return;
    final storage = ref.read(secureStorageProvider);
    if (branchId == null) {
      await storage.delete(key: 'active_branch_id');
    } else {
      await storage.write(key: 'active_branch_id', value: branchId.toString());
    }
    state = AsyncValue.data(cur.withActiveBranch(branchId));
  }

  Future<void> signOut() async {
    final cur = state.value;
    final storage = ref.read(secureStorageProvider);
    if (cur is AuthSignedIn) {
      try { await _service(cur.token).logout(); } catch (_) { /* best-effort */ }
    }
    await storage.deleteAll();
    state = const AsyncValue.data(AuthSignedOut());
  }
}

/// The ApiService configured with the current auth token + base URL.
/// Re-created whenever auth state changes so the bearer header stays fresh.
final apiServiceProvider = Provider<ApiService>((ref) {
  final base = ref.watch(apiBaseUrlProvider);
  final auth = ref.watch(authControllerProvider).value;
  final token = auth is AuthSignedIn ? auth.token : null;
  return ApiService(ApiClient(baseUrl: base, tokenProvider: () => token));
});

/// Single shared OutboxStore instance.
final outboxStoreProvider = Provider<OutboxStore>((ref) => OutboxStore());

final outboxDrainerProvider = Provider<OutboxDrainer>((ref) {
  return OutboxDrainer(
    api  : ref.watch(apiServiceProvider),
    store: ref.watch(outboxStoreProvider),
  );
});

/// Reactive pending-count for the badge in the app bar.
final outboxPendingCountProvider = FutureProvider<int>((ref) async {
  return ref.watch(outboxStoreProvider).pendingCount();
});
