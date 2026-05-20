import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
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

  static const _tabPaths = <String>['/home', '/transactions', '/shipments', '/notifications'];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l      = AppLocalizations.of(context)!;
    final client = ref.watch(authProvider).valueOrNull;
    final unread = ref.watch(notificationsProvider).valueOrNull?.unreadCount ?? 0;

    final location = GoRouterState.of(context).matchedLocation;
    final selectedIndex =
        _tabPaths.indexWhere((p) => location.startsWith(p)).clamp(0, _tabPaths.length - 1);

    final tabs = <_TabSpec>[
      _TabSpec(icon: Icons.dashboard_outlined,          active: Icons.dashboard,      label: l.tabHome),
      _TabSpec(icon: Icons.receipt_long_outlined,       active: Icons.receipt_long,   label: l.tabTransactions),
      _TabSpec(icon: Icons.local_shipping_outlined,     active: Icons.local_shipping, label: l.tabShipments),
      _TabSpec(icon: Icons.notifications_none_outlined, active: Icons.notifications,  label: l.tabNotifications),
    ];

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
            tooltip: l.settingsTitle,
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => context.push('/settings'),
          ),
        ],
      ),
      body: child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: selectedIndex,
        onDestinationSelected: (i) => context.go(_tabPaths[i]),
        destinations: <Widget>[
          for (var i = 0; i < tabs.length; i++)
            NavigationDestination(
              icon: i == 3 && unread > 0
                ? Badge.count(count: unread, child: Icon(tabs[i].icon))
                : Icon(tabs[i].icon),
              selectedIcon: Icon(tabs[i].active),
              label: tabs[i].label,
            ),
        ],
      ),
    );
  }
}

class _TabSpec {
  final IconData icon;
  final IconData active;
  final String label;
  const _TabSpec({required this.icon, required this.active, required this.label});
}
