import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'screens/activity_screen.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/queue_screen.dart';
import 'screens/scan_review_screen.dart';
import 'screens/scanner_screen.dart';
import 'state/auth_state.dart';
import 'state/providers.dart';

/// Provider-backed GoRouter so the refresh listenable can wire into the
/// Riverpod ref directly without leaking WidgetRef plumbing into the UI tree.
final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefresh(ref);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (ctx, state) {
      final auth = ref.read(authControllerProvider).value;
      final signedIn = auth is AuthSignedIn;
      final atLogin  = state.matchedLocation == '/login';

      if (auth == null || auth is AuthBooting) return null;
      if (!signedIn && !atLogin) return '/login';
      if (signedIn && atLogin)    return '/';
      return null;
    },
    routes: <RouteBase>[
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/',      builder: (_, _) => const HomeScreen()),
      GoRoute(path: '/scan',  builder: (_, _) => const ScannerScreen()),
      GoRoute(
        path: '/scan/review',
        builder: (_, st) {
          final sticker = st.uri.queryParameters['sticker'] ?? '';
          return ScanReviewScreen(stickerId: sticker);
        },
      ),
      GoRoute(
        path: '/queue/:branchId',
        builder: (_, st) => QueueScreen(branchId: int.parse(st.pathParameters['branchId']!)),
      ),
      GoRoute(path: '/activity', builder: (_, _) => const ActivityScreen()),
    ],
  );
});

/// Adapter: turns the auth-state provider into something go_router's
/// refreshListenable can listen to.
class _AuthRefresh extends ChangeNotifier {
  _AuthRefresh(Ref ref) {
    _sub = ref.listen<AsyncValue<AuthState>>(
      authControllerProvider,
      (_, _) => notifyListeners(),
      fireImmediately: false,
    );
  }
  late final ProviderSubscription<AsyncValue<AuthState>> _sub;

  @override
  void dispose() {
    _sub.close();
    super.dispose();
  }
}
