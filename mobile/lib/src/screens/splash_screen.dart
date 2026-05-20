import 'package:flutter/material.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';

/// Shown while we restore the auth token from secure storage on cold start.
/// The router replaces this with /login or /home as soon as the auth state
/// resolves — so this screen should never linger more than a few hundred ms.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    // AppLocalizations may be null briefly during the first frame on cold
    // start (delegates wire one tick after Localizations builds); the ?? fallback
    // keeps the splash from blank-frame-flickering.
    final title = AppLocalizations.of(context)?.appTitle ?? 'ShipFlow';
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            const CircularProgressIndicator(),
            const SizedBox(height: 16),
            Text(title),
          ],
        ),
      ),
    );
  }
}
