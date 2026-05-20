import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/app.dart';
import 'src/push/push_service.dart';

/// Entry point. Firebase init is gated on a build-time toggle so the
/// app still launches in a dev environment that hasn't dropped in
/// google-services.json / GoogleService-Info.plist yet.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await SystemChrome.setPreferredOrientations(<DeviceOrientation>[
    DeviceOrientation.portraitUp,
  ]);

  try {
    await Firebase.initializeApp();
    await PushService.instance.bootstrap();
  } catch (e) {
    // Without Firebase the app is still usable — push just won't fire.
    // We log to stdout so a missing google-services.json is obvious during
    // first-time setup but doesn't crash the dev loop.
    debugPrint('[main] Firebase init skipped: $e');
  }

  runApp(const ProviderScope(child: ShipFlowApp()));
}
