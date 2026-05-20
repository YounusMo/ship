import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'screens/dashboard_screen.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'screens/notifications_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/shipment_detail_screen.dart';
import 'screens/shipments_screen.dart';
import 'screens/splash_screen.dart';
import 'screens/transactions_screen.dart';
import 'state/auth_provider.dart';

/// go_router config with an auth guard. We listen to the auth state via
/// [_GoRouterRefreshStream] so the router refreshes when the user logs in
/// or out, taking them to the right place without an explicit `context.go`.
final routerProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authProvider);

  final router = GoRouter(
    initialLocation: '/',
    refreshListenable: _AuthListenable(ref),
    redirect: (context, state) {
      final loading  = auth.isLoading;
      final loggedIn = auth.valueOrNull != null;
      final goingToLogin = state.matchedLocation == '/login';
      final goingToSplash = state.matchedLocation == '/';

      // While the auth state is being restored from secure storage, stay
      // on the splash screen — bouncing to /login mid-restore creates a
      // visible flicker on cold start.
      if (loading) {
        return goingToSplash ? null : '/';
      }
      if (!loggedIn && !goingToLogin) return '/login';
      if (loggedIn && (goingToLogin || goingToSplash)) return '/home';
      return null;
    },
    routes: <RouteBase>[
      GoRoute(path: '/',      builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) => HomeShell(child: child),
        routes: <RouteBase>[
          GoRoute(path: '/home',          builder: (_, __) => const DashboardScreen()),
          GoRoute(path: '/transactions',  builder: (_, __) => const TransactionsScreen()),
          GoRoute(path: '/shipments',     builder: (_, __) => const ShipmentsScreen()),
          GoRoute(path: '/notifications', builder: (_, __) => const NotificationsScreen()),
        ],
      ),
      // Shipment detail lives OUTSIDE the bottom-nav shell so it pushes
      // a full-screen route — the back button returns to the previous tab.
      GoRoute(
        path: '/shipments/:mode/:id',
        builder: (context, state) {
          final mode = state.pathParameters['mode']!;
          final id   = int.parse(state.pathParameters['id']!);
          return ShipmentDetailScreen(mode: mode, id: id);
        },
      ),
      GoRoute(
        path: '/settings',
        builder: (_, __) => const SettingsScreen(),
      ),
    ],
  );
  activeRouter = router;
  return router;
});

/// Global handle to the active GoRouter so PushService can deep-link from
/// background notification taps without holding a BuildContext.
GoRouter? activeRouter;

/// Wires a Riverpod provider into go_router's refreshListenable contract.
/// We notify on every auth state change so the guard above re-runs.
class _AuthListenable extends ChangeNotifier {
  _AuthListenable(this._ref) {
    _ref.listen(authProvider, (_, __) => notifyListeners());
  }
  final Ref _ref;
}
