import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../state/auth_provider.dart';
import '../state/notifications_provider.dart';

/// Shell wrapping every authenticated screen — bottom tab bar + an app bar
/// with the client name and a sign-out button. The actual screen content
/// is the `child` passed in by go_router's ShellRoute.
class HomeShell extends ConsumerWidget {
  const HomeShell({super.key, required this.child});
  final Widget child;

  static const _tabs = <_TabSpec>[
    _TabSpec(path: '/home',          icon: Icons.dashboard_outlined,         active: Icons.dashboard,           label: 'Home'),
    _TabSpec(path: '/transactions',  icon: Icons.receipt_long_outlined,      active: Icons.receipt_long,        label: 'Transactions'),
    _TabSpec(path: '/shipments',     icon: Icons.local_shipping_outlined,    active: Icons.local_shipping,      label: 'Shipments'),
    _TabSpec(path: '/notifications', icon: Icons.notifications_none_outlined,active: Icons.notifications,       label: 'Alerts'),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final client = ref.watch(authProvider).valueOrNull;
    final unread = ref.watch(notificationsProvider).valueOrNull?.unreadCount ?? 0;

    final location = GoRouterState.of(context).matchedLocation;
    final selectedIndex =
        _tabs.indexWhere((t) => location.startsWith(t.path)).clamp(0, _tabs.length - 1);

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(client?.name ?? '', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            if (client?.code != null)
              Text(client!.code, style: const TextStyle(fontSize: 11, color: Colors.white70)),
          ],
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Sign out',
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
            },
          ),
        ],
      ),
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedIndex,
        onDestinationSelected: (i) => context.go(_tabs[i].path),
        destinations: <Widget>[
          for (var i = 0; i < _tabs.length; i++)
            NavigationDestination(
              icon: i == 3 && unread > 0
                ? Badge.count(count: unread, child: Icon(_tabs[i].icon))
                : Icon(_tabs[i].icon),
              selectedIcon: Icon(_tabs[i].active),
              label: _tabs[i].label,
            ),
        ],
      ),
    );
  }
}

class _TabSpec {
  final String path;
  final IconData icon;
  final IconData active;
  final String label;
  const _TabSpec({required this.path, required this.icon, required this.active, required this.label});
}
