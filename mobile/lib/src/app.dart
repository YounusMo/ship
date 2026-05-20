import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
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
      // i18n: codegen lives at .dart_tool/flutter_gen/... after `flutter pub get`.
      // Until that runs, AppLocalizations isn't importable — to keep this file
      // compileable in a fresh checkout, we wire only the delegates and the
      // supported locales here; screens look strings up via Localizations.of
      // once the codegen file is present.
      localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: const <Locale>[
        Locale('en'),
        Locale('ar'),
      ],
      routerConfig: router,
    );
  }
}
