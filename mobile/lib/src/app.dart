import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'router.dart';
import 'theme.dart';

class ShipFlowApp extends ConsumerWidget {
  const ShipFlowApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'ShipFlow',
      debugShowCheckedModeBanner: false,
      theme: shipflowLightTheme,
      darkTheme: shipflowDarkTheme,
      themeMode: ThemeMode.system,
      routerConfig: router,
    );
  }
}
