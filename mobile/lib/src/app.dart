import 'package:flutter/material.dart';
import 'package:shipflow_client/l10n/app_localizations.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'router.dart';
import 'state/locale_provider.dart';
import 'theme.dart';

class ShipFlowApp extends ConsumerWidget {
  const ShipFlowApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    // null = follow system locale; a Locale = user-pinned via Settings.
    final locale = ref.watch(localeProvider).valueOrNull;

    return MaterialApp.router(
      onGenerateTitle: (ctx) => AppLocalizations.of(ctx)!.appTitle,
      debugShowCheckedModeBanner: false,
      theme: shipflowLightTheme,
      darkTheme: shipflowDarkTheme,
      themeMode: ThemeMode.system,
      locale: locale,
      localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    );
  }
}
