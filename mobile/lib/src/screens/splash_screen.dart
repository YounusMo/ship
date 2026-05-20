import 'package:flutter/material.dart';

/// Shown while we restore the auth token from secure storage on cold start.
/// The router replaces this with /login or /home as soon as the auth state
/// resolves — so this screen should never linger more than a few hundred ms.
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('ShipFlow'),
          ],
        ),
      ),
    );
  }
}
