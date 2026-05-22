import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../l10n/app_localizations.dart';
import 'router.dart';
import 'state/providers.dart';
import 'theme.dart';

class ShipFlowEmployeeApp extends ConsumerStatefulWidget {
  const ShipFlowEmployeeApp({super.key});

  @override
  ConsumerState<ShipFlowEmployeeApp> createState() => _ShipFlowEmployeeAppState();
}

class _ShipFlowEmployeeAppState extends ConsumerState<ShipFlowEmployeeApp> {
  final Connectivity _connectivity = Connectivity();

  @override
  void initState() {
    super.initState();
    _connectivity.onConnectivityChanged.listen((results) async {
      // Any non-none transition is a hint to try draining the outbox.
      final hasNet = results.any((r) => r != ConnectivityResult.none);
      if (!hasNet || !mounted) return;
      try {
        final r = await ref.read(outboxDrainerProvider).drainOnce();
        if (r.sent > 0) ref.invalidate(outboxPendingCountProvider);
      } catch (_) { /* swallow — UI shows pending count regardless */ }
    });
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);
    return MaterialApp.router(
      title             : 'ShipFlow Employee',
      theme             : AppTheme.light(),
      routerConfig      : router,
      debugShowCheckedModeBanner: false,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales      : AppLocalizations.supportedLocales,
    );
  }
}
