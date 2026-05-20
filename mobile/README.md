# ShipFlow Client (mobile)

Flutter mobile app for MATAZ TRADING clients — balances, transactions, shipments, push notifications.

Talks to the Laravel API under `/api/*` (see `system/routes/api.php`). Auth is Sanctum personal-access-tokens issued by `POST /api/auth/login`.

## First-time setup

```bash
# 1. Install Flutter (>= 3.22). https://docs.flutter.dev/get-started/install

# 2. From this directory, fill in the platform host projects:
flutter create . --platforms=ios,android --org com.mataztrading

# 3. Install Dart deps
flutter pub get

# 4. Set the API base URL (default points at the dev server on localhost:8002)
#    For real devices, this must be reachable from the phone — use your LAN IP.
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8002
```

## Configuring push (Firebase Cloud Messaging)

1. Create a Firebase project (or reuse the one whose service-account JSON you put on the backend at `FCM_CREDENTIALS_PATH`).
2. Register an Android app → download `google-services.json` → drop into `android/app/`.
3. Register an iOS app → download `GoogleService-Info.plist` → drop into `ios/Runner/`.
4. Both files are in `.gitignore`. Never commit them.
5. In `ios/Runner/Info.plist`, add the background-modes entry that `firebase_messaging` requires.
6. On the backend, set `FCM_PROJECT_ID` to the same project id and ensure a queue worker is running (`php artisan queue:work --queue=default`).

After login, the app calls `POST /api/devices/register` with its FCM token. From that point, server-side `notify(...)` fan-outs reach the device.

## Build flavors

The app is built around a single `--dart-define=API_BASE_URL=...` switch. For multi-environment builds (staging/prod), produce a thin wrapper script:

```bash
# build-prod.sh
flutter build apk --release --dart-define=API_BASE_URL=https://api.shipflow.example.com
flutter build ios --release --dart-define=API_BASE_URL=https://api.shipflow.example.com
```

## Architecture

- **State**: Riverpod 2.x. `AsyncNotifier` for paginated endpoints; `Notifier` for auth.
- **HTTP**: Dio with two interceptors — token (auto-attaches `Authorization: Bearer …`) and error mapping (translates Dio errors into `ApiException`).
- **Routing**: go_router with an auth guard that redirects unauthenticated traffic to `/login` and authenticated traffic away from it.
- **Push**: Firebase Messaging initialized lazily on the first successful login. Tokens get re-registered on app start to handle rotation.

```
lib/
├── main.dart                  app entry
└── src/
    ├── app.dart               MaterialApp + ProviderScope + Router
    ├── router.dart            go_router config + auth guard
    ├── theme.dart             colors, typography, components
    ├── api/
    │   ├── api_client.dart    Dio instance + interceptors
    │   ├── api_service.dart   typed per-endpoint methods
    │   └── api_exceptions.dart
    ├── models/                Plain Dart classes — manual JSON (no codegen).
    ├── state/                 Riverpod providers
    ├── screens/               One file per route
    ├── widgets/               Reusable rows & cards
    └── push/
        └── push_service.dart  FCM setup + foreground display
```

## Testing on a real device against local backend

The backend dev server (`php artisan serve` on `127.0.0.1:8002`) is only reachable from the dev machine. To test from a phone on the same Wi-Fi:

```bash
# From system/
php artisan serve --port=8002 --host=0.0.0.0

# From mobile/ (replace IP with your machine's LAN address)
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8002
```

Set `SESSION_SECURE_COOKIE=false` in `system/.env` for local HTTP testing (revert before deploying — secure cookies are a production requirement).

## Status of this codebase

This is a **first cut**. Working today:
- Login → token persisted in Keychain / EncryptedSharedPreferences.
- Dashboard with per-currency balance cards.
- Paginated transactions list with currency filter.
- Paginated shipments list (received + shipped buckets, sea + sky).
- Notifications feed with unread badge and mark-read.
- FCM token registration on login.

Known gaps (next iteration):
- Shipment detail screen with per-piece tracking codes (endpoint exists; UI not yet wired).
- Deep-link from FCM payload → relevant screen.
- Biometric unlock after first login (`local_auth`).
- Pull-to-refresh on every list.
- Offline cache via `dio_cache_interceptor` or a simple SQLite layer.
- i18n: currently English-only. The backend supports `lang` per-client; the app should follow.
